<?php
namespace App\Models;

use App\Models\Common;
use App\Models\Donation;
use App\Models\User;

final class Deposit extends Common
{
    const CREATED_AT = 'added_on';
    const UPDATED_AT = 'reviewed_on';
    protected $table = 'Donut_Deposit';
    public $timestamps = true;
    protected $fillable = ['collected_from_user_id', 'given_to_user_id', 'reviewed_on', 'amount', 'status'];
    protected $national_account_user_id = 13257; // Pooja's User ID in Donut

    public function donations()
    {
        $donations = $this->belongsToMany('App\Models\Donation', 'Donut_DonationDeposit');
        return $donations->get();
    }

    public function collected_from() {
        $user = $this->belongsTo("App\Models\User", 'collected_from_user_id');
        return $user->first();
    }

    public function given_to() {
        $user = $this->belongsTo("App\Models\User", 'given_to_user_id');
        return $user->first();
    }

    public function add($collected_from_user_id, $given_to_user_id, $donation_ids) {
        // Validations...
        $user = new User;
        if(!$user->fetch($collected_from_user_id)) return $this->error("Invalid User ID of depositer.");
        if(!$user->fetch($given_to_user_id)) return $this->error("Invalid User ID of collector.");
        if($collected_from_user_id == $given_to_user_id) return $this->error("Depositer and collector can't be the same person.");

        // Check if any of the given donation has been part of an approved or pending deposit. Rejected deposits are ok.
        $donation = new Donation;
        foreach ($donation_ids as $donation_id) {
            $existing_donation = $donation->fetch($donation_id);
            if(!$existing_donation) return $this->error("Dontation $donation_id does not exist.");

            $pre_existing_deposit = false;
            foreach($existing_donation->deposit as $dep) {
                if(($dep->status == 'pending' or $dep->status == 'approved') and $dep->collected_from_user_id = $collected_from_user_id) {
                    $pre_existing_deposit = true; 
                    break;
                }
            }

            if($pre_existing_deposit) return $this->error("Dontation $donation_id is already deposited. You cannot deposit it again.");

            // :TODO: Check if this user has the ability to deposit this donation - must be a donation the user fundraised or approved at some point.
            //          Are both collected_from_user_id and given_to_user_id in the same city - except for the Finance fellow -> National deposit.
        }

        $amount = app('db')->table("Donut_Donation")->whereIn('id', $donation_ids)->sum('amount');

        // This should be Deposit::create - for for some reason its return can't be accessed - some private/protected issue, I think.
        $deposit_id = Deposit::insertGetId([
            'collected_from_user_id'=> $collected_from_user_id,
            'given_to_user_id'      => $given_to_user_id,
            'reviewed_on'           => '0000-00-00 00:00:00',
            'added_on'              => date('Y-m-d H:i:s'),
            'status'                => 'pending',
            'amount'                => $amount,
        ]);

        foreach ($donation_ids as $donation_id) {
            $donation = new Donation;
            $donation->find($donation_id)->edit([
                'status'        => 'deposited',
                //'with_user_id'  => $given_to_user_id, // This gets updated only after approved.
            ]);

            app('db')->table("Donut_DonationDeposit")->insert([
                'donation_id'   => $donation_id,
                'deposit_id'    => $deposit_id
            ]);
        }

        return $this->find($deposit_id);
    }

    public function search($data) 
    {
        $q = app('db')->table($this->table);

        $q->select("Donut_Deposit.id","Donut_Deposit.amount","Donut_Deposit.added_on","Donut_Deposit.reviewed_on","Donut_Deposit.status","Donut_Deposit.collected_from_user_id","Donut_Deposit.given_to_user_id");

        if(!empty($data['reviewer_id'])) {
            $q->where('Donut_Deposit.given_to_user_id', $data['reviewer_id']);
            $q->where('Donut_Deposit.status', 'pending');
        }

        if(!empty($data['id'])) $q->where('Donut_Deposit.id', $data['id']);
        if(!empty($data['status'])) $q->where('Donut_Deposit.status', $data['status']);
        if(!empty($data['status_in'])) $q->whereIn('Donut_Deposit.status', $data['status_in']);

        $donation = new Donation;
        $q->where('Donut_Deposit.added_on', '>', $donation->start_date);

        $q->orderBy('Donut_Deposit.added_on','desc');
        $deposits = $q->get();
        // dd($q->toSql(), $q->getBindings());

        foreach ($deposits as $index => $dep) {
            $deposits[$index]->donations = $this->find($dep->id)->donations();
        }

        return $deposits;
    }

    public function approve($current_user_id, $deposit_id = false) {
        $this->chain($deposit_id);

        if(!$current_user_id) return $this->error("Please include the ID of the user reviewing the deposit.");

        $donations = $this->item->donations();
        foreach ($donations as $donation) {
            $donation->edit([
                'status'        => 'collected',
                'with_user_id'  => $current_user_id,
                'updated_by_user_id' => $current_user_id
            ]);
        }

        return $this->changeStatus('approved', $current_user_id);
    }

    public function reject($current_user_id, $deposit_id = false) {
        $this->chain($deposit_id);

        return $this->changeStatus('rejected', $current_user_id);
    }

    public function changeStatus($status, $current_user_id, $deposit_id = false) {
        $this->chain($deposit_id);

        if(!$this->item) return false;
        if($this->item->given_to_user_id != $current_user_id) return $this->error("Current user don't have permission to approve/reject the deposit.");

        $this->item->status = $status;
        return $this->item->save();
    }

    public function fetch($deposit_id)
    {
        $deposit = $this->find($deposit_id);
        if(!$deposit) return false;

        $deposit->donations = $deposit->donations();

        return $deposit;
    }
}