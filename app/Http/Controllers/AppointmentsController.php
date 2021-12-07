<?php

namespace App\Http\Controllers;

use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\User;
use App\City;
use App\Doctor;
use App\Image;
use App\Speciality;
use App\UserRole;
use App\Appointment;
use App\TimeTable;


class AppointmentsController extends Controller
{    
    /**
     * Method mobileAppShowAll
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function mobileAppShowAll(Request $request)
    {
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request->api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        if ($user == null) {
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        } else {
            $appointments = Appointment::where('user_id', $user->id)->orderBy('appointment_time', 'desc')->get();

            if ($appointments->isEmpty()) {
                $isFailed = true;
                $errors += [
                    'error' => 'no appointments yet'
                ];
            } else {
                foreach ($appointments as $app) {
                    $id = $app->id;
                    $doc_id = $app->doctor_id;
                    $doc = Doctor::where('id', $doc_id)->first();
                    $add = $doc->address;
                    if ($add == NULL) {
                        $add = 'Not set';
                    }
                    $isCallUp = $app->is_callup;
                    if ($isCallUp == 1) {
                        $fees = $doc->callup_fees;
                        if ($fees == NULL) {
                            $fees = 0;
                        }
                    } else {
                        $fees = $doc->fees;
                        if ($fees == NULL) {
                            $fees = 0;
                        }
                    }
                    $docDur = $doc->duration;
                    if ($docDur == NULL) {
                        $docDur = 0;
                    }
                    $reExamine = $app->re_examination;
                    if ($reExamine == 1) {
                        $checkup = $app->last_checkup;
                    } else {
                        $checkup = null;
                    }
                    $spec_id = $doc->speciality_id;
                    if ($spec_id == NULL) {
                        $spec = 'Not set';
                    } else {
                        $spec = Speciality::find($spec_id)->name;
                    }
                    $Uid = $doc->user_id;
                    $user_doc = User::find($Uid);
                    $img_id = $user_doc->image_id;
                    $image = Image::where('id', $img_id)->first();
                    if ($image != null) {
                        $image_path = Storage::url($image->path . '.' . $image->extension);
                        $image_url = asset($image_path);
                    } else {
                        $image_url = asset(Storage::url('default/doctor.png'));
                    }
                    //response
                    $appointment = [
                        'id' => $id,
                        'full_name' => $user_doc->full_name,
                        'address' => $add,
                        'image' => $image_url,
                        'speciality' => $spec,
                        'app_time' => $app->appointment_time,
                        'duration' => $docDur,
                        'fees' => $fees,
                        'last_checkup' => $checkup,
                        'is_callup' => $app->is_callup,
                        're_exam' => $app->re_examination,
                    ];
                    $data[] = $appointment;
                }
            }
        }
        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
    
    /**
     * Method mobileCreateAppointment
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function mobileCreateAppointment(Request $request)
    {
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request->api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        if ($user == null) {
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        } else {
            $doctor_id = $request->doctor_id;
            $user_id = $user->id;
            $appointment_time = $request->appointment_time;
            $is_callup = $request->is_callup;
            $duration = 1;
            $doc = Doctor::where('id', $doctor_id)->first();
            if (Appointment::where(['doctor_id' => $doctor_id, 'appointment_time' => $appointment_time])->count() == 0) {
                $appointment = new Appointment;
                $appointment->user_id = $user_id;
                $appointment->doctor_id = $doctor_id;
                $appointment->appointment_time = $appointment_time;
                $appointment->is_callup = $is_callup;
                //                $appointment -> duration = $doc -> duration;

                if ($appointment->save()) {
                    $data += [
                        'success' => 'appointment booked successfully',
                    ];
                } else {
                    $isFailed = true;
                    $errors += [
                        'error' => 'could not register your appointment',
                    ];
                }
            } else {
                $isFailed = true;
                $errors += [
                    'error' => 'an appointment already exists at this time',
                ];
            }
        }

        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
    
    /**
     * Method mobileShowAvailable
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function mobileShowAvailable(Request $request)
    {
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request->api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        $first_day_app = [];
        $first_day = null;
        $day_1 = null;
        $second_day_app = [];
        $second_day = null;
        $day_2 = null;

        if ($user == null) {
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        } else {
            // Get the doctor's ID
            $doctor_id = $request->doctor_id;
            $doctor_data = Doctor::find($doctor_id);
            if ($doctor_data != null) {
                $doctor_user_id = User::select('id')->where('id', $doctor_data->user_id)->first()->id;
                // details about doctor's schedule in certain days
                // The doctor's schedule
                $doctor_timetables = TimeTable::where('user_id', $doctor_user_id)->orderBy('day_id', 'asc')->get();
                if ($doctor_timetables->isEmpty()) {
                    $isFailed = true;
                    $errors += [
                        'error' => 'this doctor do not have a schedule yet',
                    ];
                } else {
                    $this_day1 = Carbon::today();
                    // search for the first available 2 days for the next 7 days
                    $m = 1;
                    while ($m <= 7) { // if edited edit the comment above ^
                        if ($first_day_app == []) {
                            $this_day_id = $this_day1->dayOfWeek;
                            if ($this_day_id == 6) {
                                $this_day_id = 1;
                            } else {
                                $this_day_id = $this_day_id + 2;
                            }
                            // get the doctor's schedule of the first available day
                            foreach ($doctor_timetables as $doctor_timetable) {
                                if ($this_day_id == $doctor_timetable->day_id) {
                                    $this_day_appointments = Appointment::where(['doctor_id' => $doctor_id])
                                        ->whereDate('appointment_time', $this_day1)->get();
                                    $this_day_appointments_no = $this_day_appointments->count();
                                    $from = Carbon::parse($doctor_timetable->from);
                                    $to = Carbon::parse($doctor_timetable->to);
                                    //                                    get duration in hours
                                    $hour_duration = ($doctor_data->duration) / 60;
                                    // each appointment is estimated to last 1 Hour
                                    $appointments_no = $to->diffInHours($from) / $hour_duration;
                                    if ($this_day_appointments->isNotEmpty()) {
                                        if ($this_day_appointments_no < $appointments_no) {
                                            $time = $this_day1->addHours($from->hour);
                                            for ($i = 0; $i < $appointments_no; $i++) {
                                                //  Check if the doctor has an appointment in the time specified, if not, adds the record
                                                if (Appointment::where(['doctor_id' => $doctor_id, 'appointment_time' => $time])->count() == 0) {
                                                    $first_day_app[] = $time->toTimeString();
                                                }
                                                ($time)->addMinutes($doctor_data->duration);
                                            }
                                            $first_day = $doctor_timetable;
                                            $day_1 = $this_day1;
                                            break 2;
                                        } else {
                                            break;
                                        }
                                    } else {
                                        // this means that this day is empty
                                        $time = $this_day1->addHours($from->hour);
                                        for ($i = 0; $i < $appointments_no; $i++) {
                                            $first_day_app[] = $time->toTimeString();
                                            ($time)->addMinutes($doctor_data->duration);
                                        }
                                        $first_day = $doctor_timetable;
                                        $day_1 = $this_day1;
                                        break 2;
                                    }
                                }
                            }
                            $this_day1->addDays(1);
                        }
                        if ($first_day_app != []) {
                            break;
                        }
                        $m++;
                    }

                    $this_day2 = Carbon::today();
                    $this_day2->addDays(1);
                    $n = 1;

                    while ($n <= 7) {
                        if ($first_day_app != []) {
                            $this_day_id = $this_day2->dayOfWeek;
                            if ($this_day_id == 6) {
                                $this_day_id = 1;
                            } else {
                                $this_day_id = $this_day_id + 2;
                            }
                            // Get the doctor's schedule of the second available day
                            foreach ($doctor_timetables as $doctor_timetable2) {
                                if ($doctor_timetable2 == $first_day) {
                                    continue;
                                }
                                if ($this_day_id == $doctor_timetable2->day_id) {
                                    $this_day_appointments2 = Appointment::where(['doctor_id' => $doctor_id])
                                        ->whereDate('appointment_time', $this_day2)->get();
                                    $this_day_appointments_no2 = $this_day_appointments2->count();
                                    $from2 = Carbon::parse($doctor_timetable2->from);
                                    $to2 = Carbon::parse($doctor_timetable2->to);
                                    // each appointment is estimated to last 1 Hour
                                    $hour_duration = ($doctor_data->duration) / 60;
                                    $appointments_no2 = $to2->diffInHours($from2) / $hour_duration;
                                    if ($this_day_appointments2->isNotEmpty()) {
                                        if ($this_day_appointments_no2 < $appointments_no2) {
                                            $time2 = $this_day2->addHours($from2->hour);
                                            for ($i = 0; $i < $appointments_no2; $i++) {
                                                //  Check if the doctor has an appointment in the time specified, if not, adds the record
                                                if (Appointment::where(['doctor_id' => $doctor_id, 'appointment_time' => $time2])->count() == 0) {
                                                    $second_day_app[] = $time2->toTimeString();
                                                }
                                                ($time2)->addMinutes($doctor_data->duration);
                                            }
                                            $second_day = $doctor_timetable2;
                                            $day_2 = $this_day2;
                                            break 2;
                                        } else {
                                            break;
                                        }
                                    } else {
                                        // this means that this day is empty
                                        $time2 = $this_day2->addHours($from2->hour);
                                        for ($i = 0; $i < $appointments_no2; $i++) {
                                            $second_day_app[] = $time2->toTimeString();
                                            ($time2)->addMinutes($doctor_data->duration);
                                        }
                                        $second_day = $doctor_timetable2;
                                        $day_2 = $this_day2;
                                        break 2;
                                    }
                                }
                            }
                            $this_day2->addDays(1);
                        }
                        if ($second_day_app != []) {
                            break;
                        }
                        $n++;
                    }
                    if (($first_day_app == []) && ($second_day_app == [])) {
                        $isFailed = true;
                        $errors += [
                            'error' => 'this doctor does not have available appointments for the next 7 days',
                        ];
                    }
                }
            } else {
                $isFailed = true;
                $errors += [
                    'error' => 'this is not a doctor, ya 3omar ya "ZAKI"',
                ];
            }
        }

        if ($isFailed == false) {
            $first_day_data = [
                'from' => $first_day->from,
                'to' => $first_day->to,
                'date' => $day_1->toDateString(),
                'name' => $day_1->englishDayOfWeek,
                'available' => $first_day_app,
            ];
            $second_day_data = [
                'from' => $second_day->from,
                'to' => $second_day->to,
                'date' => $day_2->toDateString(),
                'name' => $day_2->englishDayOfWeek,
                'available' => $second_day_app,
            ];
            $data = [
                'first_day' => $first_day_data,
                'second_day' => $second_day_data,
            ];
        }


        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
    //for web

    /* Web Tailored Functions */
    /* ***************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    ******************************************************************************************************
    */

    
    /**
     * Method webShowAll
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function webShowAll(Request $request)
    {
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request->api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        if ($user == null) {
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        } else {
            $appointments = Appointment::where('user_id', $user->id)->orderBy('appointment_time', 'desc')->get();

            if ($appointments->isEmpty()) {
                $isFailed = true;
                $errors += [
                    'error' => 'no appointments yet'
                ];
            } else {
                foreach ($appointments as $app) {
                    $id = $app->id;
                    $doc_id = $app->doctor_id;
                    $doc = Doctor::where('id', $doc_id)->first();
                    $add = $doc->address;
                    if ($add == NULL) {
                        $add = 'Not set';
                    }
                    $isCallUp = $app->is_callup;
                    if ($isCallUp == 1) {
                        $fees = $doc->callup_fees;
                        if ($fees == NULL) {
                            $fees = 0;
                        }
                    } else {
                        $fees = $doc->fees;
                        if ($fees == NULL) {
                            $fees = 0;
                        }
                    }
                    $docDur = $doc->duration;
                    if ($docDur == NULL) {
                        $docDur = 0;
                    }
                    $reExamine = $app->re_examination;
                    if ($reExamine == 1) {
                        $checkup = $app->last_checkup;
                    } else {
                        $checkup = null;
                    }
                    $spec_id = $doc->speciality_id;
                    if ($spec_id == NULL) {
                        $spec = 'Not set';
                    } else {
                        $spec = Speciality::find($spec_id)->name;
                    }
                    $Uid = $doc->user_id;
                    $user_doc = User::find($Uid);
                    $img_id = $user_doc->image_id;
                    $image = Image::where('id', $img_id)->first();
                    if ($image != null) {
                        $image_path = Storage::url($image->path . '.' . $image->extension);
                        $image_url = asset($image_path);
                    } else {
                        $image_url = asset(Storage::url('default/doctor.png'));
                    }
                    //response
                    $appointment = [
                        'id' => $id,
                        'full_name' => $user_doc->full_name,
                        'address' => $add,
                        'image' => $image_url,
                        'speciality' => $spec,
                        'app_time' => $app->appointment_time,
                        'duration' => $docDur,
                        'fees' => $fees,
                        'last_checkup' => $checkup,
                        'is_callup' => $app->is_callup,
                        're_exam' => $app->re_examination,
                    ];
                    $data[] = $appointment;
                }
            }
        }
        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
        
    /**
     * Method webCreateAppointment
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function webCreateAppointment(Request $request)
    {
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request->api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        if ($user == null) {
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        } else {
            $doctor_id = $request->doctor_id;
            $user_id = $user->id;
            $appointment_time = $request->appointment_time;
            $is_callup = $request->is_callup;
            $duration = 1;
            $doc = Doctor::where('id', $doctor_id)->first();
            if (Appointment::where(['doctor_id' => $doctor_id, 'appointment_time' => $appointment_time])->count() == 0) {
                $appointment = new Appointment;
                $appointment->user_id = $user_id;
                $appointment->doctor_id = $doctor_id;
                $appointment->appointment_time = $appointment_time;
                $appointment->is_callup = $is_callup;
                //                $appointment -> duration = $doc -> duration;

                if ($appointment->save()) {
                    $data += [
                        'success' => 'appointment booked successfully',
                    ];
                } else {
                    $isFailed = true;
                    $errors += [
                        'error' => 'could not register your appointment',
                    ];
                }
            } else {
                $isFailed = true;
                $errors += [
                    'error' => 'an appointment already exists at this time',
                ];
            }
        }

        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
    
    /**
     * Method webShowAvailable
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function webShowAvailable(Request $request)
    {
        $isFailed = false;
        $data = [];
        $errors =  [];

        $api_token = $request->api_token;
        $user = null;
        $user = User::where('api_token', $api_token)->first();

        $first_day_app = [];
        $first_day = null;
        $day_1 = null;
        $second_day_app = [];
        $second_day = null;
        $day_2 = null;

        if ($user == null) {
            $isFailed = true;
            $errors += [
                'auth' => 'authentication failed'
            ];
        } else {
            // Get the doctor's ID
            $doctor_id = $request->doctor_id;
            $doctor_data = Doctor::find($doctor_id);
            if ($doctor_data != null) {
                $doctor_user_id = User::select('id')->where('id', $doctor_data->user_id)->first()->id;
                // details about doctor's schedule in certain days
                // The doctor's schedule
                $doctor_timetables = TimeTable::where('user_id', $doctor_user_id)->orderBy('day_id', 'asc')->get();
                if ($doctor_timetables->isEmpty()) {
                    $isFailed = true;
                    $errors += [
                        'error' => 'this doctor do not have a schedule yet',
                    ];
                } else {
                    $this_day1 = Carbon::today();
                    // search for the first available 2 days for the next 7 days
                    $m = 1;
                    while ($m <= 7) { // if edited edit the comment above ^
                        if ($first_day_app == []) {
                            $this_day_id = $this_day1->dayOfWeek;
                            if ($this_day_id == 6) {
                                $this_day_id = 1;
                            } else {
                                $this_day_id = $this_day_id + 2;
                            }
                            // get the doctor's schedule of the first available day
                            foreach ($doctor_timetables as $doctor_timetable) {
                                if ($this_day_id == $doctor_timetable->day_id) {
                                    $this_day_appointments = Appointment::where(['doctor_id' => $doctor_id])
                                        ->whereDate('appointment_time', $this_day1)->get();
                                    $this_day_appointments_no = $this_day_appointments->count();
                                    $from = Carbon::parse($doctor_timetable->from);
                                    $to = Carbon::parse($doctor_timetable->to);
                                    //                                    get duration in hours
                                    $hour_duration = ($doctor_data->duration) / 60;
                                    // each appointment is estimated to last 1 Hour
                                    $appointments_no = $to->diffInHours($from) / $hour_duration;
                                    if ($this_day_appointments->isNotEmpty()) {
                                        if ($this_day_appointments_no < $appointments_no) {
                                            $time = $this_day1->addHours($from->hour);
                                            for ($i = 0; $i < $appointments_no; $i++) {
                                                //  Check if the doctor has an appointment in the time specified, if not, adds the record
                                                if (Appointment::where(['doctor_id' => $doctor_id, 'appointment_time' => $time])->count() == 0) {
                                                    $first_day_app[] = $time->toTimeString();
                                                }
                                                ($time)->addMinutes($doctor_data->duration);
                                            }
                                            $first_day = $doctor_timetable;
                                            $day_1 = $this_day1;
                                            break 2;
                                        } else {
                                            break;
                                        }
                                    } else {
                                        // this means that this day is empty
                                        $time = $this_day1->addHours($from->hour);
                                        for ($i = 0; $i < $appointments_no; $i++) {
                                            $first_day_app[] = $time->toTimeString();
                                            ($time)->addMinutes($doctor_data->duration);
                                        }
                                        $first_day = $doctor_timetable;
                                        $day_1 = $this_day1;
                                        break 2;
                                    }
                                }
                            }
                            $this_day1->addDays(1);
                        }
                        if ($first_day_app != []) {
                            break;
                        }
                        $m++;
                    }

                    $this_day2 = Carbon::today();
                    $this_day2->addDays(1);
                    $n = 1;

                    while ($n <= 7) {
                        if ($first_day_app != []) {
                            $this_day_id = $this_day2->dayOfWeek;
                            if ($this_day_id == 6) {
                                $this_day_id = 1;
                            } else {
                                $this_day_id = $this_day_id + 2;
                            }
                            // Get the doctor's schedule of the second available day
                            foreach ($doctor_timetables as $doctor_timetable2) {
                                if ($doctor_timetable2 == $first_day) {
                                    continue;
                                }
                                if ($this_day_id == $doctor_timetable2->day_id) {
                                    $this_day_appointments2 = Appointment::where(['doctor_id' => $doctor_id])
                                        ->whereDate('appointment_time', $this_day2)->get();
                                    $this_day_appointments_no2 = $this_day_appointments2->count();
                                    $from2 = Carbon::parse($doctor_timetable2->from);
                                    $to2 = Carbon::parse($doctor_timetable2->to);
                                    // each appointment is estimated to last 1 Hour
                                    $hour_duration = ($doctor_data->duration) / 60;
                                    $appointments_no2 = $to2->diffInHours($from2) / $hour_duration;
                                    if ($this_day_appointments2->isNotEmpty()) {
                                        if ($this_day_appointments_no2 < $appointments_no2) {
                                            $time2 = $this_day2->addHours($from2->hour);
                                            for ($i = 0; $i < $appointments_no2; $i++) {
                                                //  Check if the doctor has an appointment in the time specified, if not, adds the record
                                                if (Appointment::where(['doctor_id' => $doctor_id, 'appointment_time' => $time2])->count() == 0) {
                                                    $second_day_app[] = $time2->toTimeString();
                                                }
                                                ($time2)->addMinutes($doctor_data->duration);
                                            }
                                            $second_day = $doctor_timetable2;
                                            $day_2 = $this_day2;
                                            break 2;
                                        } else {
                                            break;
                                        }
                                    } else {
                                        // this means that this day is empty
                                        $time2 = $this_day2->addHours($from2->hour);
                                        for ($i = 0; $i < $appointments_no2; $i++) {
                                            $second_day_app[] = $time2->toTimeString();
                                            ($time2)->addMinutes($doctor_data->duration);
                                        }
                                        $second_day = $doctor_timetable2;
                                        $day_2 = $this_day2;
                                        break 2;
                                    }
                                }
                            }
                            $this_day2->addDays(1);
                        }
                        if ($second_day_app != []) {
                            break;
                        }
                        $n++;
                    }
                    if (($first_day_app == []) && ($second_day_app == [])) {
                        $isFailed = true;
                        $errors += [
                            'error' => 'this doctor does not have available appointments for the next 7 days',
                        ];
                    }
                }
            } else {
                $isFailed = true;
                $errors += [
                    'error' => 'this is not a doctor, ya 3omar ya "ZAKI"',
                ];
            }
        }

        if ($isFailed == false) {
            $first_day_data = [
                'from' => $first_day->from,
                'to' => $first_day->to,
                'date' => $day_1->toDateString(),
                'name' => $day_1->englishDayOfWeek,
                'available' => $first_day_app,
            ];
            $second_day_data = [
                'from' => $second_day->from,
                'to' => $second_day->to,
                'date' => $day_2->toDateString(),
                'name' => $day_2->englishDayOfWeek,
                'available' => $second_day_app,
            ];
            $data = [
                'first_day' => $first_day_data,
                'second_day' => $second_day_data,
            ];
        }


        $response = [
            'isFailed' => $isFailed,
            'data' => $data,
            'errors' => $errors
        ];

        return response()->json($response);
    }
}
