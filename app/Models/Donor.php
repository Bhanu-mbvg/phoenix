<?php
namespace App\Models;

use App\Models\Common;
use App\Models\Donation;

final class Donor extends Common
{
    const CREATED_AT = 'added_on';
    const UPDATED_AT = 'updated_on';
    protected $table = 'Donut_Donor';
    public $timestamps = true;
    protected $fillable = ['name','sex','birthday','center_id','status','added_on', 'description', 'photo'];

    public function donation()
    {
        $donations = $this->hasMany('App\Models\Donation', 'donor_id');
        return $donations->get();
    }

    /// Used to find a donor who matches multiple fields - email and phone. If none found, create the user.
    public function findMatching($data, $added_by_user_id = 0) {
        // Find the donor - both email and phone must be same
        $donor = app('db')->table($this->table)->select('id')->where('email', $data['donor_email'])->where('phone', $data['donor_phone'])->first();

        // If we can't find the donor, add a new one.
        if(!$donor) {
            $donor_id = Donor::create([
                'name'  => $data['donor_name'],
                'email' => $data['donor_email'],
                'phone' => $data['donor_phone'],
                'address' => $data['donor_address'],
                'added_by_user_id' => $added_by_user_id
            ]);
            return $donor_id;
        } 

        return $donor['id'];
    }

    // public function search($data)
    // {
    //     $q = app('db')->table($this->table);

    //     $q->select("Student.id","Student.name","Student.added_on","Student.sex", "Student.status", "Student.center_id", "Student.birthday", "Student.description",
    //                     app('db')->raw("Center.name AS center_name"));
    //     $q->join("Center", "Center.id", '=', 'Student.center_id');

    //     if(!isset($data['status'])) $data['status'] = 1;
    //     if($data['status'] !== false) $q->where('Student.status', $data['status']); // Setting status as '0' gets you even the deleted students
        
    //     if(isset($data['center_id']) and $data['center_id'] != 0) $q->where('Student.center_id', $data['center_id']);
        
    //     if(!empty($data['id'])) $q->where('Student.id', $data['id']);
    //     if(!empty($data['student_id'])) $q->where('Student.id', $data['student_id']);
    //     if(!empty($data['city_id'])) $q->where('Center.city_id', $data['city_id']);
    //     if(!empty($data['name'])) $q->where('Student.name', 'like', '%' . $data['name'] . '%');
    //     if(!empty($data['sex'])) $q->where('Student.sex', $data['sex']);

    //     if(!empty($data['level_id'])) {
    //         $q->join('StudentLevel', 'Student.id', '=', 'StudentLevel.student_id');
    //         $q->where('StudentLevel.level_id', $data['level_id']);
    //     }

    //     $results = $q->get();
        
    //     return $results;
    // }

    // public function fetch($student_id) {
    //     $data = Student::where('status','1')->find($student_id);
    //     if(!$data) return false;

    //     $this->id = $student_id;
    //     $this->student = $data;

    //     $data->center = $data->center()->name;
    //     return $data;
    // }

    // public function inCenter($center_id) {
    //     return $this->search(['center_id' => $center_id]);
    // }

    // public function add($data)
    // {
    //     $student = Student::create([
    //         'name'      => $data['name'],
    //         'sex'       => isset($data['sex']) ? $data['sex'] : 'u',
    //         'birthday'  => isset($data['birthday']) ? date('Y-m-d', strtotime($data['birthday'])) : '',
    //         'center_id' => $data['center_id'],
    //         'description'   => isset($data['description']) ? $data['description'] : '',
    //         'photo'     => isset($data['photo']) ? $data['photo'] : '',
    //         'status'    => isset($data['status']) ? $data['status'] : '1',
    //         'added_on'  => isset($data['added_on']) ? $data['added_on'] : date('Y-m-d H:i:s')
    //     ]);

    //     return $student;
    // }
}
