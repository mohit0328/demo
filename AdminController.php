<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Validator;
use Session;
use App\Models\Category;
use App\Models\CoachCategory;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Appointment;
use App\Models\Payment;
use App\PasswordReset;
use App\Notifications\AdminPasswordResetRequest;
use App\Notifications\AdminPasswordResetSuccess;
use Stripe;

class AdminController extends ApiController
{
    // admin login
    public function adminLogin()
    {
        $Auth_type = [];

        //check email format
        $is_email = preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix", request('email'));
        if ($is_email == 1) {
            $email = DB::table('users')
                ->where([['email', '=', request('email')], ['status', '=', 1]])
                ->first();
        } else {
            return response()->json(['message' => 'Invalid email format', "status" => false], JsonResponse::HTTP_BAD_REQUEST);
        }

        // check auth and other conditions
        if (!empty($email)) {
            $Auth_type = Auth::attempt(['email' => request('email'), 'password' => request('password'), 'status' => '1']);
            if (!empty($Auth_type)) {
                $user = Auth::user();

                $input = request();

                $user->last_name = $user->last_name != null ? $user->last_name : "";
                $user->full_name = $user->full_name != null ? $user->full_name : "";
                $user->phone = $user->phone != null ? $user->phone : "";
                $user->profile = $user->profile != null ? $user->profile : "";
                $user->original_password = ($user->original_pasword != null) ? $user->original_password : "";
                $user->authtoken = $user->createToken('MyApp')->accessToken;
                $user->profile = $user->profile ? img_url . $user->profile : img_url . 'user_profile/defultUser.png';
                $usr = User::select('id', 'full_name', 'email', 'is_email_verified', 'current_lang', 'user_type')
                    ->where('id', $user->id)
                    ->first();
                $usr['profile'] = $user->profile;
                $usr['authtoken'] = $user->authtoken;

                if ($user->user_type == "3") {
                    if ($user->is_email_verified != "1") {
                        return response()->json(['message' => 'Admin email is not verified', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
                    } else {
                        return response()->json(['message' => 'Login Success', 'record' => $usr, 'status' => true], JsonResponse::HTTP_OK);
                    }
                } else {
                    return response()->json(['message' => 'You are not authorised', 'status' => false], JsonResponse::HTTP_UNAUTHORIZED);
                }
            }
            else{
                return response()->json(["message" => 'Invalid_email_password', "status" => false], JsonResponse::HTTP_UNAUTHORIZED);
            }
        } else {
            return response()->json(['message' => 'Invalid Email', 'status' => false], JsonResponse::HTTP_UNAUTHORIZED);
        }
    }

    // password forget
    public function forgetPassword(Request $request)
    {
        // return "success";
        $data = json_decode($request->getContent(), true);
        $validator = Validator::make($data, [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Email verification error 400', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }else{
            $user = User::where('email', '=', $request->email)->first();
            if (!$user) {
                return response()->json(['message' => 'Email is not match', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
            }else {
                $token = Str::random(6);
                $forgetPassword = PasswordReset::updateOrCreate(
                    ['email' => $user->email],
                    [
                        'email' => $user->email,
                        'token' => $token,
                    ]
                );
                if ($user && $forgetPassword) {
                    $user->notify(new AdminPasswordResetRequest($forgetPassword->token));
                }
                return response()->json(['message' => 'Password reset successfull', 'status' => true], JsonResponse::HTTP_OK);
            }
        }
    }

    // change password
    public function changePassword(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $validator = Validator::make($data, [
            'email' => 'required|string|email',
            'temporary_pass' => 'required',
            'password' => 'required|min:6',
            'password_confirmation' => 'required|min:6|same:password',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }

        $adminPasswordReset = PasswordReset::where(DB::raw('BINARY `token`'), $request->temporary_pass)
        ->where([['email', '=', $request->email]])->first();
        if (!$adminPasswordReset) {
            return response()->json(['message' => trans('passwords.token'), 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }

        if (Carbon::parse($adminPasswordReset->updated_at)->addMinutes(60)->isPast()) {
            $adminPasswordReset->delete();
            return response()->json(['message' => trans('passwords.expired'), 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($adminPasswordReset) {
            
            $user = User::where([['email', '=', $adminPasswordReset->email], ['status', '=', 1]])->first();
            if (!$user) {
                return response()->json(['message' => trans('passwords.user'), 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
            }

            $user->password = bcrypt($request->password);
            $user->original_password = $request->password;
            $user->save();
            $adminPasswordReset->delete();
            $user->notify(new AdminPasswordResetSuccess($adminPasswordReset));
            return response()->json(['message' => trans('passwords.reset'), 'status' => true], JsonResponse::HTTP_OK);
        }
    }

    // number of user & coach count
    public function numberOfUsersAndCoach()
    {
        // dd('success');
        $userCount = User::where('user_type','=','1')->count();
        $coachCount = User::where('user_type','=','2')->count();

        if(!empty($userCount) && !empty($coachCount)){
            return response()->json(['message' => 'Success', 'users' => $userCount, 'coaches' => $coachCount, 'status' => true], JsonResponse::HTTP_OK);
        }else{
            return response()->json(['message' => 'No Users', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }
        // return $userCount;
    }

    // number of appointment count
    public function numberOfAppointment()
    {
        $appointment = Appointment::count();
        if(!empty($appointment)){
            return response()->json(['message' => 'Success', 'record' => $appointment, 'status' => true], JsonResponse::HTTP_OK);
        }else{
            return response()->json(['message' => 'No Appointments', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    // get our stripe customer
    public function getPayUserList()
    {
        // $userLists = Payment::with('userPay')->select('payments.*')->get();
        $paymentList = Payment::with('userPay:id,full_name')->paginate(10);
        $result = [];
        if (!empty($paymentList)) {
            foreach($paymentList as $record)
            {             
                // $record['coachName'] = $this->getCoachName($record->coach_id);
                $record['coachName'] = getCoachName($record->coach_id);
                $result[] = $record;
            }
            $finalData['paymentList'] = $result;
            $finalData['currentPage'] = $paymentList->currentPage();
            $finalData['last_page'] = $paymentList->lastPage();
            $finalData['total_record'] = $paymentList->total();
            $finalData['per_page'] = $paymentList->perPage();
            return response()->json(['message' => 'Success', 'record' => $finalData, 'status' => true], JsonResponse::HTTP_OK);
        }else{
            return response()->json(['message' => 'No users payments', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    // get users list
    public function userList()
    {
        $userList = User::where('user_type', '=', '1')->paginate(10);
        // $userList = User::where('user_type', '=', '1')->paginate(2);
        $result = [];
        if (!empty($userList)) {
            // $userList[] = $userList;
            foreach($userList as $list)
            {
                $list->created_on = $list->created_on ? date('d-m-Y H:i', $list->created_on) : "";
                $list->updated_on = $list->updated_on ? date('d-m-Y H:i', $list->updated_on) : "";
                
                $result[] = $list;
            }
            $finalData['userList'] = $result;
            $finalData['currentPage'] = $userList->currentPage();
            $finalData['last_page'] = $userList->lastPage();
            $finalData['total_record'] = $userList->total();
            $finalData['per_page'] = $userList->perPage();
            return response()->json(['message' => 'Success', 'record' => $finalData, 'status' => true], JsonResponse::HTTP_OK);
        }else{
            return response()->json(['message' => 'No Users Found', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    // coach list
    public function coachList()
    {
        $admin = Auth::user();
        
        $allCoach = User::select('users.*','categories.name as categoryName')
        ->leftjoin('coach_categories', 'coach_categories.coach_id', '=', 'users.id')
        ->leftjoin('categories', 'categories.id', '=', 'coach_categories.category_id')
        ->where([['users.status', '=', '1'], ['users.user_type', '=', '2']])
        ->paginate(10);

        $result = [];
        if (!empty($allCoach)) {
            foreach ($allCoach as $coach) {
              
                $coach->created_on = $coach->created_on ? date('d-m-Y H:i', $coach->created_on) : "";
                $coach->updated_on = $coach->updated_on ? date('d-m-Y H:i', $coach->updated_on) : "";
                
                    $result[] = $coach;
                }
            $finalData['allCoach'] = $result;
            $finalData['currentPage'] = $allCoach->currentPage();
            $finalData['last_page'] = $allCoach->lastPage();
            $finalData['total_record'] = $allCoach->total();
            $finalData['per_page'] = $allCoach->perPage();
            return response()->json(['record' => $finalData,"status" => true], JsonResponse::HTTP_OK);
            }
        else
        {
            return response()->json(["error" => trans('customMessages.something_wrong'),"status" => false], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    // for user details
    public function userDetails($userId='')
    {
        $userDetail = User::where('id',$userId)->first();
        $userAppointment = Appointment::where('user_id',$userId)->get();
        $userPayment = Payment::where('user_id',$userId)->get();
        if (!empty($userDetail)) {
            if (!empty($userAppointment)) {
                $appointment = $userAppointment;
            }else{
                $appointment = [];
            }

            if (!empty($userPayment)) {
                $payments = $userPayment;
            }else{
                $payments = [];
            }

            $userDetail['profile'] = $userDetail['profile'] ? img_url . $userDetail['profile'] : img_url . 'user_profile/defaultUser.png';

            $userInfo['userName'] = $userDetail['full_name'];
            $userInfo['email'] = $userDetail['email'];
            $userInfo['profile'] = $userDetail['profile'];
            
            return response()->json(['message' => 'Success', 'record' => $userInfo, 'appointment' => $appointment, 'payments' => $payments, 'status' => true], JsonResponse::HTTP_OK);
        }else{
            return response()->json(['message' => 'No data', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    // for coach details
    public function coachDetails($coachId='')
    {
        $coachDetails = User::where('id',$coachId)->first();
        $coachAppointment = Appointment::where('coach_id',$coachId)->get();
        $coachPayment = Payment::where('coach_id',$coachId)->get();
        
        if (!empty($coachDetails)) 
        {
            if (!empty($coachAppointment)) {
                $appointment = $coachAppointment;
            }else{
                $appointment = [];
            }

            if (!empty($coachPayment)) {
                $payments = $coachPayment;
            }else{
                $payments = [];
            }

            $coachDetails['profile'] = $coachDetails['profile'] ? img_url . $coachDetails['profile'] : img_url . 'user_profile/defaultUser.png';

            $coachInfo['coachName'] = $coachDetails['full_name'];
            $coachInfo['email'] = $coachDetails['email'];
            $coachInfo['profile'] = $coachDetails['profile'];

            return response()->json(['message' => 'Success', 'record' => $coachInfo, 'appointment' => $appointment, 'payments' => $payments, 'status' => true], JsonResponse::HTTP_OK);
        }else{
            return response()->json(['message' => 'No data', 'status' => false], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    // for Logout
    public function logout()
    {
        $user = Auth::user()->token();
        $user_id = Auth::user()->id;
        $update['fcm_token'] = '';
        $query = DB::table('users')
        ->where('id',$user_id)
        ->update($update);
        // $user->revoke();
        return response()->json(['message' => 'Logout Successful', 'status' => true], JsonResponse::HTTP_OK);
    }


    public function getImage($id)
   {
        $url= 'https://clippingmagic.com/api/v1/images/'.$id;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Basic MTE4MjM6dGNlcHVrZmRiMDhtMzNrNzhjbW1iNjgwNzczazdmOGRlNzZsMjI4azAyYWhnZXBxMzhlMQ=='));
        $data = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
        // Save result
        file_put_contents("https://mmfinfotech.co/Ranch_App/storage/app/public/ranch_images_video/", $data); // Save the file to disk, TODO: change the filename / store more permanently
        } else {
        echo "Error: " . $data;
        }
        curl_close($ch);
   }
}
