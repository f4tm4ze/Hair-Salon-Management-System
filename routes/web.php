<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Tech\DashboardController as TechDashboardController;
use App\Http\Controllers\Tech\AppointmentController as TechAppointmentController;
use App\Http\Controllers\Auth\InvitationController;

// Invitation routes (public)
Route::get('/invite/accept/{token}', [InvitationController::class, 'showAcceptForm'])->name('invitation.accept');
Route::post('/invite/accept/{token}', [InvitationController::class, 'accept'])->name('invitation.accept');

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('auth')->group(function () {
        Route::post('/verify-password', [App\Http\Controllers\PasswordController::class, 'verify'])->name('verify-password');
    });
});

Route::get('/auth/{provider}/redirect', [App\Http\Controllers\Auth\SocialiteController::class, 'redirect'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [App\Http\Controllers\Auth\SocialiteController::class, 'callback'])->name('social.callback');
Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');


// Public customer routes (no auth required)
Route::get('/', [App\Http\Controllers\Customer\HomeController::class, 'index'])->name('home');
Route::get('/services', [App\Http\Controllers\Customer\ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [App\Http\Controllers\Customer\ServiceController::class, 'show'])->name('services.show');
Route::get('/about', [App\Http\Controllers\Customer\HomeController::class, 'about'])->name('about');

// Authenticated customer routes
Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    // Dashboard (My Profile)
    Route::get('/', [App\Http\Controllers\Customer\DashboardController::class, 'index'])->name('dashboard');

    // Appointments
    Route::get('/appointments', [App\Http\Controllers\Customer\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/create', [App\Http\Controllers\Customer\AppointmentController::class, 'create'])->name('appointments.create');
    Route::post('/appointments', [App\Http\Controllers\Customer\AppointmentController::class, 'store'])->name('appointments.store');
    Route::get('/appointments/{appointment}', [App\Http\Controllers\Customer\AppointmentController::class, 'show'])->name('appointments.show');
    Route::post('/appointments/{appointment}/cancel', [App\Http\Controllers\Customer\AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::get('/appointments/{appointment}/edit', [App\Http\Controllers\Customer\AppointmentController::class, 'edit'])->name('appointments.edit');
    Route::put('/appointments/{appointment}', [App\Http\Controllers\Customer\AppointmentController::class, 'update'])->name('appointments.update');

    // Deactivate account
    Route::post('/deactivate', [App\Http\Controllers\Customer\DashboardController::class, 'deactivate'])->name('deactivate');
});



// Beauty Tech routes
Route::middleware(['auth', 'role:tech'])->prefix('tech')->name('tech.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Tech\DashboardController::class, 'index'])->name('dashboard');

    // Appointments
    Route::get('/appointments', [App\Http\Controllers\Tech\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/{appointment}', [App\Http\Controllers\Tech\AppointmentController::class, 'show'])->name('appointments.show');
    Route::patch('/appointments/{appointment}/complete', [App\Http\Controllers\Tech\AppointmentController::class, 'complete'])->name('appointments.complete');

    // Service History
    Route::get('/history', [App\Http\Controllers\Tech\HistoryController::class, 'index'])->name('history.index');
});



// Front Desk routes
Route::middleware(['auth', 'role:frontdesk'])->prefix('frontdesk')->name('frontdesk.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\FrontDesk\DashboardController::class, 'index'])->name('dashboard');

    // Appointments
    Route::resource('appointments', App\Http\Controllers\FrontDesk\AppointmentController::class)->except(['destroy']);
    Route::get('/appointments/{appointment}/assign', [App\Http\Controllers\FrontDesk\AppointmentController::class, 'assignForm'])->name('appointments.assign');
    Route::post('/appointments/{appointment}/assign', [App\Http\Controllers\FrontDesk\AppointmentController::class, 'assign'])->name('appointments.assign.employee');
    Route::patch('/appointments/{appointment}/complete', [App\Http\Controllers\FrontDesk\AppointmentController::class, 'complete'])->name('appointments.complete');
    Route::patch('/appointments/{appointment}/mark-paid', [App\Http\Controllers\FrontDesk\AppointmentController::class, 'markPaid'])->name('appointments.mark-paid');
    Route::post('/appointments/{appointment}/cancel', [App\Http\Controllers\FrontDesk\AppointmentController::class, 'cancel'])->name('appointments.cancel');

    // Customers (with archive/restore)
    Route::get('/customers/archived', [App\Http\Controllers\FrontDesk\CustomerController::class, 'archived'])->name('customers.archived');
    Route::get('/customers/create', [App\Http\Controllers\FrontDesk\CustomerController::class, 'create'])->name('customers.create');
    Route::get('/customers', [App\Http\Controllers\FrontDesk\CustomerController::class, 'index'])->name('customers.index');
    Route::post('/customers', [App\Http\Controllers\FrontDesk\CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}/edit', [App\Http\Controllers\FrontDesk\CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [App\Http\Controllers\FrontDesk\CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [App\Http\Controllers\FrontDesk\CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::get('/customers/{customer}', [App\Http\Controllers\FrontDesk\CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/history', [App\Http\Controllers\FrontDesk\CustomerController::class, 'history'])->name('customers.history');
    Route::post('/customers/{id}/restore', [App\Http\Controllers\FrontDesk\CustomerController::class, 'restore'])->name('customers.restore');

    // Payments
    Route::get('/payments', [App\Http\Controllers\FrontDesk\PaymentController::class, 'index'])->name('payments.index');
    Route::patch('/payments/{appointment}/validate', [App\Http\Controllers\FrontDesk\PaymentController::class, 'validatePayment'])->name('payments.validate');
});



// Admin (Branch Manager) routes
Route::middleware(['auth', 'role:manager'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/audit-logs', [App\Http\Controllers\Admin\AuditLogController::class, 'index'])->name('audit_logs.index');

    Route::post('/verify-password', [App\Http\Controllers\Admin\PasswordController::class, 'verify'])->name('admin.verify-password');

    // Appointments 
    Route::get('/appointments/archived', [App\Http\Controllers\Admin\AppointmentController::class, 'archived'])->name('appointments.archived');
    Route::post('/appointments/{id}/restore', [App\Http\Controllers\Admin\AppointmentController::class, 'restore'])->name('appointments.restore');
    Route::resource('appointments', App\Http\Controllers\Admin\AppointmentController::class);

    Route::get('/appointments/{appointment}', [App\Http\Controllers\Admin\AppointmentController::class, 'show'])->name('appointments.show');
    Route::get('/appointments/{appointment}/assign', [App\Http\Controllers\Admin\AppointmentController::class, 'assignForm'])->name('appointments.assign.form');
    Route::post('/appointments/{appointment}/assign', [App\Http\Controllers\Admin\AppointmentController::class, 'assign'])->name('appointments.assign');
    Route::post('/appointments/{appointment}/cancel', [App\Http\Controllers\Admin\AppointmentController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/appointments/{appointment}/mark-paid', [App\Http\Controllers\Admin\AppointmentController::class, 'markPaid'])->name('appointments.mark-paid');
    Route::post('/appointments/{appointment}/complete', [App\Http\Controllers\Admin\AppointmentController::class, 'complete'])->name('appointments.complete');

    // Customers
    Route::get('/customers/archived', [App\Http\Controllers\Admin\CustomerController::class, 'archived'])->name('customers.archived');
    Route::post('/customers/{id}/restore', [App\Http\Controllers\Admin\CustomerController::class, 'restore'])->name('customers.restore');
    Route::resource('customers', App\Http\Controllers\Admin\CustomerController::class);
    Route::get('/customers/{customer}', [App\Http\Controllers\Admin\CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/history', [App\Http\Controllers\Admin\CustomerController::class, 'history'])->name('customers.history');

    // Employees 
    Route::get('/employees', [App\Http\Controllers\Admin\EmployeeController::class, 'index'])->name('employees.index');

    Route::post('/employees/{employee}/resend-invitation', [App\Http\Controllers\Admin\EmployeeController::class, 'resendInvitation'])->name('employees.resend-invitation');
    Route::delete('/employees/{employee}/cancel-invite', [App\Http\Controllers\Admin\EmployeeController::class, 'destroyInvite'])->name('employees.destroy-invite');

    Route::get('/employees/create', [App\Http\Controllers\Admin\EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [App\Http\Controllers\Admin\EmployeeController::class, 'store'])->name('employees.store');
    Route::get('/employees/{employee}/edit', [App\Http\Controllers\Admin\EmployeeController::class, 'edit'])->name('employees.edit');
    Route::put('/employees/{employee}', [App\Http\Controllers\Admin\EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('/employees/{employee}', [App\Http\Controllers\Admin\EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::get('/employees/archived', [App\Http\Controllers\Admin\EmployeeController::class, 'archived'])->name('employees.archived');
    Route::post('/employees/{id}/restore', [App\Http\Controllers\Admin\EmployeeController::class, 'restore'])->name('employees.restore');
    Route::get('/employees/{employee}', [App\Http\Controllers\Admin\EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/employees/{employee}/history', [App\Http\Controllers\Admin\EmployeeController::class, 'history'])->name('employees.history');


    // Services
    Route::resource('services', App\Http\Controllers\Admin\ServiceController::class)->except(['show']);
    Route::get('/services/archived', [App\Http\Controllers\Admin\ServiceController::class, 'archived'])->name('services.archived');
    Route::post('/services/{id}/restore', [App\Http\Controllers\Admin\ServiceController::class, 'restore'])->name('services.restore');
    Route::get('/services/{service}', [App\Http\Controllers\Admin\ServiceController::class, 'show'])->name('services.show');

    Route::prefix('services')->group(function () {
        Route::post('/{service}/products', [App\Http\Controllers\Admin\ServiceProductController::class, 'attach'])->name('services.products.attach');
        Route::put('/{service}/products/{product}', [App\Http\Controllers\Admin\ServiceProductController::class, 'updateQuantity'])->name('services.products.update');
        Route::delete('/{service}/products/{product}', [App\Http\Controllers\Admin\ServiceProductController::class, 'detach'])->name('services.products.detach');
    });

    // Products
    Route::resource('products', App\Http\Controllers\Admin\ProductController::class)->except(['show']);
    Route::get('/products/archived', [App\Http\Controllers\Admin\ProductController::class, 'archived'])->name('products.archived');
    Route::post('/products/{id}/restore', [App\Http\Controllers\Admin\ProductController::class, 'restore'])->name('products.restore');

    // Discounts
    Route::resource('discounts', App\Http\Controllers\Admin\DiscountController::class)->except(['show']);
    Route::get('/discounts/archived', [App\Http\Controllers\Admin\DiscountController::class, 'archived'])->name('discounts.archived');
    Route::post('/discounts/{id}/restore', [App\Http\Controllers\Admin\DiscountController::class, 'restore'])->name('discounts.restore');

    // Reports
    Route::get('/reports', [App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
});

require __DIR__ . '/auth.php';
