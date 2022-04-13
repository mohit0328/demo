<?php
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\CommunAuthController;
use App\Http\Controllers\API\EmailVerifcationController;
use App\Http\Controllers\StaticContentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\API\AdminController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::group(['middleware' => [App\Http\Middleware\Localization::class]], function () {
    // password reset routs
    Route::post('reset/create', [App\Http\Controllers\API\PasswordResetController::class, 'create']);
    Route::post('reset/find_and_reset', [App\Http\Controllers\API\PasswordResetController::class, 'find']);
    Route::post('verify', [App\Http\Controllers\API\EmailVerifcationController::class, 'confirm']);
    // register email and mobile number
    Route::post('common/register', [App\Http\Controllers\API\CommunAuthController::class, 'register']);
    // login with email and mobile
    Route::post('common/login', [App\Http\Controllers\API\CommunAuthController::class, 'login']);
  
    // check email verifiaction done or not
    Route::post('common/checkVerifyStatus', [App\Http\Controllers\API\CommunAuthController::class, 'emailVerifyStatus']);
    // resend link only for user
    Route::post('user/resendLink', [App\Http\Controllers\API\UserController::class, 'resendVerificationLink']);

    

    // confirm verification
    // confirm verification with common
    Route::post('common/verifyWithOTP', [App\Http\Controllers\API\EmailVerifcationController::class, 'confirmWithOTP']);
    // Route::post('reset', [App\Http\Controllers\API\PasswordResetController::class, 'reset']);
    // resend otp
    Route::post('common/resend_otp', [App\Http\Controllers\API\EmailVerifcationController::class, 'resendOTP']);
    // confirm verification with OTP sent by SMS
    Route::post('common/verifyWithSmsOTP', [App\Http\Controllers\API\EmailVerifcationController::class, 'confirmWithSmsOTP']);
    // No need of app  secret because it will hit on browser

    // onBoarding apis

    // Auth::routes(['verify' => true]);



        // user apis
    Route::group(['middleware' => 'auth:api'], function () {
        
        
        // logout apis for coach and user
        Route::get('common/logout', [App\Http\Controllers\API\CommunAuthController::class, 'logout']);
        // change password
        Route::post('common/changePassword', [App\Http\Controllers\API\CommunAuthController::class, 'changePassword']);

        Route::get('user/details', [App\Http\Controllers\API\UserController::class, 'userDetails']);
        Route::post('user/listOfCoach', [App\Http\Controllers\API\UserController::class, 'coachList']);
        Route::post('user/changeDetails', [App\Http\Controllers\API\UserController::class, 'updateInfo']);
        
        Route::get('user/coachDetails/{coach_id?}', [App\Http\Controllers\API\UserController::class, 'coachDetails']);

        // appointments
        Route::post('user/availableSLots', [App\Http\Controllers\API\UserController::class, 'availableSLots']);
        Route::post('user/appointments', [App\Http\Controllers\API\UserController::class, 'bookSlot']);
        Route::get('user/myAppointment', [App\Http\Controllers\API\UserController::class, 'myBookings']);
        // change email and verification
        // Route::post('user/change_email_otp', [App\Http\Controllers\API\EmailVerifcationController::class, 'changeEmailOTP']);
        // Route::post('user/verifyNewEmailWithOTP', [App\Http\Controllers\API\EmailVerifcationController::class, 'confirmNewEmailWithOTP']);
        //  // changes name and profile image
        // delete user account
        Route::post('common/delete', [App\Http\Controllers\API\CommunAuthController::class, 'deleteAccount']);
        // commun api for category list
        Route::get('category/list', [App\Http\Controllers\API\CoachController::class, 'categoryList']);

        // payment
        Route::get('payment/users_pay_history', [App\Http\Controllers\PaymentController::class, 'pay_history_user']);

        // static data for all roles
        Route::get('common/content', [App\Http\Controllers\StaticContentController::class, 'staticContent']);

        // supports for all roles
        Route::post('common/support_form', [App\Http\Controllers\SupportController::class, 'support']);

    });

    // coach apis
    Route::group(['middleware' => ['auth:api', 'is_coach']], function () {
        
        Route::post('coach/completeRegistration', [App\Http\Controllers\API\CoachController::class, 'chooseCategory']);

        Route::post('coach/saveCard', [App\Http\Controllers\API\CoachController::class, 'cardDetails']);
        Route::get('coach/details', [App\Http\Controllers\API\CoachController::class, 'coachDetails']);
        Route::post('coach/changeDetails', [App\Http\Controllers\API\CoachController::class, 'updateInfo']);

        // my appointments
        Route::get('coach/myAppointment', [App\Http\Controllers\API\CoachController::class, 'myBookings']);
        
        Route::get('coach/schedules', [App\Http\Controllers\API\CoachController::class, 'coachSchedules']);
        Route::get('schedule/delete/{scedule_id?}', [App\Http\Controllers\API\CoachController::class, 'delete']);
        Route::post('schedule/toggle', [App\Http\Controllers\API\CoachController::class, 'toggleDays']);
        Route::post('schedule/changeOrCreate', [App\Http\Controllers\API\CoachController::class, 'createOrupdate']);
        
        // payment
        Route::get('payment/coach_pay_history', [App\Http\Controllers\PaymentController::class, 'pay_history_coach']);
    });

    // admin apis
    Route::post('admin/login', [AdminController::class, 'adminLogin']);
    
    Route::group(['middleware' => ['auth:api', 'is_admin']], function () {
        // Route::get('admin/listOfCoach', [AdminController::class, 'coachList']);
        Route::post('admin/forgetPassword', [AdminController::class, 'forgetPassword']);
        Route::post('admin/changePassword', [AdminController::class, 'changePassword']);
        Route::get('admin/numberofusers', [AdminController::class, 'numberOfUsersAndCoach']);
        Route::get('admin/numberofAppointment', [AdminController::class, 'numberOfAppointment']);
        Route::get('admin/getPayUserList', [AdminController::class, 'getPayUserList']);
        Route::get('admin/userList', [AdminController::class, 'userList']);
        Route::get('admin/coachList', [AdminController::class, 'coachList']);
        Route::get('admin/userDetails/{userId?}',[AdminController::class, 'userDetails']);
        Route::get('admin/coachDetails/{coachId?}',[AdminController::class, 'coachDetails']);
        Route::get('admin/logout',[AdminController::class, 'logout']);
    });

    Route::post('checkFirebase',[App\Http\Controllers\Admin\AdminController::class, 'checkFirebaseNotification']);
    Route::get('user/getImage/{id}', [AdminController::class, 'getImage']);
});
