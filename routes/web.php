<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\BranchManagementController;
use App\Http\Controllers\BranchSelectionController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryManagementController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\Auth\AuthenticatedSessionController as CustomerAuthenticatedSessionController;
use App\Http\Controllers\Customer\Auth\RegisteredUserController as CustomerRegisteredUserController;
use App\Http\Controllers\CustomerManagementController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\HeroSliderController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemManagementController;
use App\Http\Controllers\MenuItemScheduleController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OptionGroupManagementController;
use App\Http\Controllers\OrderActionController;
use App\Http\Controllers\OrderDashboardController;
use App\Http\Controllers\OrderHistoryController;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionManagementController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ReportsInvoicesController;
use App\Http\Controllers\ReviewManagementController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\Rider\AuthenticatedSessionController as RiderAuthenticatedSessionController;
use App\Http\Controllers\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Rider\DeliveryHistoryController as RiderDeliveryHistoryController;
use App\Http\Controllers\Rider\ProfileController as RiderProfileController;
use App\Http\Controllers\RiderAvailabilityController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\StaffMemberManagementController;
use App\Http\Controllers\TodayReportController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WeeklyReportController;
use App\Http\Controllers\WorkingHoursController;
use Illuminate\Support\Facades\Route;

// track.visit records an anonymous first-party-cookie visit for every
// page in the actual browse-to-order funnel — home through tracking —
// so the Performance page's conversion rate (orders ÷ visits) means
// something. Deliberately not applied anywhere under /dashboard, /rider,
// or the customer auth routes below.
Route::middleware('track.visit')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
    Route::get('/menu/{menuItem:slug}', [MenuController::class, 'show'])->name('menu.show');

    Route::get('/branches', [BranchesController::class, 'index'])->name('branches.index');
    Route::get('/branches/{branch}/select', [BranchesController::class, 'select'])->name('branches.pick');

    Route::get('/contact', [ContactController::class, 'index'])->name('contact');
    Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/faq', [FaqController::class, 'index'])->name('faq');

    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

    Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{line}', [CartController::class, 'updateQuantity'])->name('cart.update');
    Route::delete('/cart/{line}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/checkout/apply-promo', [CheckoutController::class, 'applyPromoCode'])->name('checkout.apply-promo');
    Route::get('/checkout/{order:track_token}/confirmation', [CheckoutController::class, 'confirmation'])->name('checkout.confirmation');
    Route::get('/checkout/{order:track_token}/paystack-return', [CheckoutController::class, 'paystackReturn'])->name('checkout.paystack-return');
    Route::get('/checkout/{order:track_token}/declined', [CheckoutController::class, 'declined'])->name('checkout.declined');

    Route::get('/track', [TrackingController::class, 'lookup'])->name('tracking.lookup');
    Route::post('/track/find', [TrackingController::class, 'find'])->name('tracking.find');
    Route::get('/track/{order:track_token}', [TrackingController::class, 'show'])->name('tracking.show');
    Route::get('/track/{order:track_token}/data', [TrackingController::class, 'data'])->name('tracking.data');
    Route::post('/track/{order:track_token}/review', [TrackingController::class, 'storeReview'])->name('tracking.review.store');

    Route::get('/reviews', [ReviewsController::class, 'index'])->name('reviews.index');

    Route::get('/terms-and-conditions', [PolicyController::class, 'terms'])->name('policy.terms');
    Route::get('/refund-policy', [PolicyController::class, 'refunds'])->name('policy.refunds');
    Route::get('/privacy-policy', [PolicyController::class, 'privacy'])->name('policy.privacy');
    Route::get('/cookie-policy', [PolicyController::class, 'cookies'])->name('policy.cookies');
});

// Distinct /customer/* paths — staff auth already owns /login, /register,
// /logout in routes/auth.php, and these would otherwise collide exactly.
Route::middleware('guest:customer')->group(function () {
    Route::get('/customer/register', [CustomerRegisteredUserController::class, 'create'])->name('customer.register');
    Route::post('/customer/register', [CustomerRegisteredUserController::class, 'store']);

    Route::get('/customer/login', [CustomerAuthenticatedSessionController::class, 'create'])->name('customer.login');
    Route::post('/customer/login', [CustomerAuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth:customer')->group(function () {
    Route::post('/customer/logout', [CustomerAuthenticatedSessionController::class, 'destroy'])->name('customer.logout');
});

Route::middleware(['auth', 'verified', 'branch', 'password.change_required', 'staff.web_orders_check'])->group(function () {
    Route::get('/dashboard', [OrderDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/performance', [PerformanceController::class, 'index'])->name('dashboard.performance');
    Route::get('/dashboard/orders', [OrderDashboardController::class, 'data'])->name('dashboard.orders.data');
    Route::get('/dashboard/orders/history', [OrderHistoryController::class, 'index'])->name('dashboard.orders.history');
    Route::get('/dashboard/orders/live', [OrderDashboardController::class, 'live'])->name('dashboard.orders.live');
    Route::get('/dashboard/orders/{order}', [OrderHistoryController::class, 'show'])->name('dashboard.orders.show');

    Route::get('/dashboard/pos', [PosController::class, 'index'])->name('dashboard.pos.index');
    Route::post('/dashboard/pos/cart/add', [PosController::class, 'addItem'])->name('dashboard.pos.cart.add');
    Route::post('/dashboard/pos/cart/{line}', [PosController::class, 'updateQuantity'])->name('dashboard.pos.cart.update');
    Route::delete('/dashboard/pos/cart/{line}', [PosController::class, 'removeItem'])->name('dashboard.pos.cart.remove');
    Route::post('/dashboard/pos/orders', [PosController::class, 'store'])->name('dashboard.pos.orders.store');

    Route::post('/dashboard/orders/{order}/accept', [OrderActionController::class, 'accept'])->name('orders.accept');
    Route::post('/dashboard/orders/{order}/reject', [OrderActionController::class, 'reject'])->name('orders.reject');
    Route::post('/dashboard/orders/{order}/advance', [OrderActionController::class, 'advance'])->name('orders.advance');
    Route::post('/dashboard/orders/{order}/cancel', [OrderActionController::class, 'cancel'])->name('orders.cancel');
    Route::post('/dashboard/orders/{order}/assign-rider', [OrderActionController::class, 'assignRider'])->name('orders.assign_rider');
    Route::post('/dashboard/orders/{order}/confirm-momo-payment', [OrderActionController::class, 'confirmMomoPayment'])->name('orders.confirm_momo_payment');
    Route::post('/dashboard/orders/{order}/refunds', [RefundController::class, 'store'])->name('orders.refunds.store');

    Route::get('/dashboard/refunds', [RefundController::class, 'index'])->name('dashboard.refunds.index');
    Route::post('/dashboard/refunds/{refund}/approve', [RefundController::class, 'approve'])->name('dashboard.refunds.approve');
    Route::post('/dashboard/refunds/{refund}/deny', [RefundController::class, 'deny'])->name('dashboard.refunds.deny');
    Route::post('/dashboard/refunds/{refund}/complete', [RefundController::class, 'complete'])->name('dashboard.refunds.complete');

    Route::get('/dashboard/reviews', [ReviewManagementController::class, 'index'])->name('dashboard.reviews.index');
    Route::post('/dashboard/reviews/{review}/approve', [ReviewManagementController::class, 'approve'])->name('dashboard.reviews.approve');
    Route::post('/dashboard/reviews/{review}/reject', [ReviewManagementController::class, 'reject'])->name('dashboard.reviews.reject');

    Route::get('/dashboard/riders', [RiderAvailabilityController::class, 'index'])->name('dashboard.riders');

    Route::get('/dashboard/shift', [ShiftController::class, 'show'])->name('shift.show');
    Route::post('/dashboard/shift/start', [ShiftController::class, 'start'])->name('shift.start');
    Route::post('/dashboard/shift/end', [ShiftController::class, 'end'])->name('shift.end');

    Route::get('/dashboard/users', [UserManagementController::class, 'index'])->name('dashboard.users.index');
    Route::get('/dashboard/users/create', [UserManagementController::class, 'create'])->name('dashboard.users.create');
    Route::post('/dashboard/users', [UserManagementController::class, 'store'])->name('dashboard.users.store');
    Route::get('/dashboard/users/{user}/edit', [UserManagementController::class, 'edit'])->name('dashboard.users.edit');
    Route::post('/dashboard/users/{user}/roles', [UserManagementController::class, 'addRole'])->name('dashboard.users.roles.add');
    Route::delete('/dashboard/users/{user}/roles', [UserManagementController::class, 'removeRole'])->name('dashboard.users.roles.remove');
    Route::post('/dashboard/users/{user}/change-branch', [UserManagementController::class, 'changeBranch'])->name('dashboard.users.change_branch');
    Route::delete('/dashboard/users/{user}', [UserManagementController::class, 'destroy'])->name('dashboard.users.destroy');

    Route::get('/dashboard/branches', [BranchManagementController::class, 'index'])->name('dashboard.branches.index');
    Route::get('/dashboard/branches/create', [BranchManagementController::class, 'create'])->name('dashboard.branches.create');
    Route::post('/dashboard/branches', [BranchManagementController::class, 'store'])->name('dashboard.branches.store');
    Route::get('/dashboard/branches/{branch}/edit', [BranchManagementController::class, 'edit'])->name('dashboard.branches.edit');
    Route::put('/dashboard/branches/{branch}', [BranchManagementController::class, 'update'])->name('dashboard.branches.update');
    Route::post('/dashboard/branches/{branch}/toggle-accepting-orders', [BranchManagementController::class, 'toggleAcceptingOrders'])->name('dashboard.branches.toggle-accepting-orders');
    Route::post('/dashboard/branches/{branch}/image', [BranchManagementController::class, 'updateImage'])->name('dashboard.branches.image.update');
    Route::delete('/dashboard/branches/{branch}/image', [BranchManagementController::class, 'destroyImage'])->name('dashboard.branches.image.destroy');

    Route::get('/dashboard/working-hours', [WorkingHoursController::class, 'index'])->name('dashboard.working-hours.index');
    Route::put('/dashboard/working-hours', [WorkingHoursController::class, 'update'])->name('dashboard.working-hours.update');

    Route::get('/dashboard/settings', [SettingsController::class, 'index'])->name('dashboard.settings.index');
    Route::put('/dashboard/settings', [SettingsController::class, 'update'])->name('dashboard.settings.update');

    Route::get('/dashboard/staff-members', [StaffMemberManagementController::class, 'index'])->name('dashboard.staff-members.index');
    Route::get('/dashboard/staff-members/create', [StaffMemberManagementController::class, 'create'])->name('dashboard.staff-members.create');
    Route::post('/dashboard/staff-members', [StaffMemberManagementController::class, 'store'])->name('dashboard.staff-members.store');
    Route::get('/dashboard/staff-members/{staffMember}/edit', [StaffMemberManagementController::class, 'edit'])->name('dashboard.staff-members.edit');
    Route::put('/dashboard/staff-members/{staffMember}', [StaffMemberManagementController::class, 'update'])->name('dashboard.staff-members.update');
    Route::post('/dashboard/staff-members/{staffMember}/image', [StaffMemberManagementController::class, 'updateImage'])->name('dashboard.staff-members.image.update');
    Route::delete('/dashboard/staff-members/{staffMember}/image', [StaffMemberManagementController::class, 'destroyImage'])->name('dashboard.staff-members.image.destroy');
    Route::delete('/dashboard/staff-members/{staffMember}', [StaffMemberManagementController::class, 'destroy'])->name('dashboard.staff-members.destroy');

    Route::get('/dashboard/promotions', [PromotionManagementController::class, 'index'])->name('dashboard.promotions.index');
    Route::get('/dashboard/promotions/create', [PromotionManagementController::class, 'create'])->name('dashboard.promotions.create');
    Route::post('/dashboard/promotions', [PromotionManagementController::class, 'store'])->name('dashboard.promotions.store');
    Route::get('/dashboard/promotions/{promotion}/edit', [PromotionManagementController::class, 'edit'])->name('dashboard.promotions.edit');
    Route::put('/dashboard/promotions/{promotion}', [PromotionManagementController::class, 'update'])->name('dashboard.promotions.update');
    Route::delete('/dashboard/promotions/{promotion}', [PromotionManagementController::class, 'destroy'])->name('dashboard.promotions.destroy');

    Route::get('/dashboard/customers', [CustomerManagementController::class, 'index'])->name('dashboard.customers.index');
    Route::get('/dashboard/customers/export', [CustomerManagementController::class, 'export'])->name('dashboard.customers.export');

    Route::get('/dashboard/reports', [ReportsController::class, 'index'])->name('dashboard.reports.index');

    Route::get('/dashboard/reports/invoices', [ReportsInvoicesController::class, 'index'])->name('dashboard.reports.invoices.index');
    Route::get('/dashboard/reports/invoices/download/{format}', [ReportsInvoicesController::class, 'download'])->name('dashboard.reports.invoices.download');

    Route::get('/dashboard/reports/weekly', [WeeklyReportController::class, 'index'])->name('dashboard.reports.weekly.index');
    Route::get('/dashboard/reports/weekly/download', [WeeklyReportController::class, 'download'])->name('dashboard.reports.weekly.download');

    Route::get('/dashboard/reports/today', [TodayReportController::class, 'index'])->name('dashboard.reports.today.index');

    Route::get('/dashboard/categories', [CategoryManagementController::class, 'index'])->name('dashboard.categories.index');
    Route::get('/dashboard/categories/create', [CategoryManagementController::class, 'create'])->name('dashboard.categories.create');
    Route::post('/dashboard/categories', [CategoryManagementController::class, 'store'])->name('dashboard.categories.store');
    Route::get('/dashboard/categories/{category}/edit', [CategoryManagementController::class, 'edit'])->name('dashboard.categories.edit');
    Route::put('/dashboard/categories/{category}', [CategoryManagementController::class, 'update'])->name('dashboard.categories.update');
    Route::post('/dashboard/categories/{category}/image', [CategoryManagementController::class, 'updateImage'])->name('dashboard.categories.image.update');
    Route::delete('/dashboard/categories/{category}/image', [CategoryManagementController::class, 'destroyImage'])->name('dashboard.categories.image.destroy');

    Route::get('/dashboard/hero-slider', [HeroSliderController::class, 'index'])->name('dashboard.hero-slider.index');

    Route::get('/dashboard/menu-items', [MenuItemManagementController::class, 'index'])->name('dashboard.menu-items.index');
    Route::get('/dashboard/menu-items/availability', [MenuItemManagementController::class, 'availability'])->name('dashboard.menu-items.availability');
    Route::get('/dashboard/menu-items/create', [MenuItemManagementController::class, 'create'])->name('dashboard.menu-items.create');
    Route::post('/dashboard/menu-items', [MenuItemManagementController::class, 'store'])->name('dashboard.menu-items.store');
    Route::get('/dashboard/menu-items/{menuItem}/edit', [MenuItemManagementController::class, 'edit'])->name('dashboard.menu-items.edit');
    Route::put('/dashboard/menu-items/{menuItem}', [MenuItemManagementController::class, 'update'])->name('dashboard.menu-items.update');
    Route::delete('/dashboard/menu-items/{menuItem}', [MenuItemManagementController::class, 'destroy'])->name('dashboard.menu-items.destroy');
    Route::post('/dashboard/menu-items/{menuItem}/toggle-availability', [MenuItemManagementController::class, 'toggleAvailability'])->name('dashboard.menu-items.toggle-availability');
    Route::post('/dashboard/menu-items/{menuItem}/image', [MenuItemManagementController::class, 'updateImage'])->name('dashboard.menu-items.image.update');
    Route::delete('/dashboard/menu-items/{menuItem}/image', [MenuItemManagementController::class, 'destroyImage'])->name('dashboard.menu-items.image.destroy');

    Route::post('/dashboard/menu-items/{menuItem}/components', [MenuItemManagementController::class, 'storeComponent'])->name('dashboard.menu-items.components.store');
    Route::delete('/dashboard/menu-items/{menuItem}/components/{component}', [MenuItemManagementController::class, 'destroyComponent'])->name('dashboard.menu-items.components.destroy');

    Route::get('/dashboard/menu-items/timetable', [MenuItemScheduleController::class, 'index'])->name('dashboard.menu-items.timetable');
    Route::post('/dashboard/menu-items/{menuItem}/schedule', [MenuItemScheduleController::class, 'update'])->name('dashboard.menu-items.schedule.update');
    Route::delete('/dashboard/menu-items/{menuItem}/schedule', [MenuItemScheduleController::class, 'destroy'])->name('dashboard.menu-items.schedule.destroy');

    Route::get('/dashboard/option-groups', [OptionGroupManagementController::class, 'index'])->name('dashboard.option-groups.index');
    Route::get('/dashboard/option-groups/create', [OptionGroupManagementController::class, 'create'])->name('dashboard.option-groups.create');
    Route::post('/dashboard/option-groups', [OptionGroupManagementController::class, 'store'])->name('dashboard.option-groups.store');
    Route::get('/dashboard/option-groups/{optionGroup}/edit', [OptionGroupManagementController::class, 'edit'])->name('dashboard.option-groups.edit');
    Route::put('/dashboard/option-groups/{optionGroup}', [OptionGroupManagementController::class, 'update'])->name('dashboard.option-groups.update');
    Route::post('/dashboard/option-groups/{optionGroup}/options', [OptionGroupManagementController::class, 'storeOption'])->name('dashboard.option-groups.options.store');
    Route::put('/dashboard/option-groups/{optionGroup}/options/{option}', [OptionGroupManagementController::class, 'updateOption'])->name('dashboard.option-groups.options.update');
    Route::delete('/dashboard/option-groups/{optionGroup}/options/{option}', [OptionGroupManagementController::class, 'destroyOption'])->name('dashboard.option-groups.options.destroy');
});

// Same `web` guard and `users` table as staff — just a separate path and
// entry point, per the "riders get their own section" decision. Riders are
// never a distinct guard (see CLAUDE.md's identity model).
Route::middleware('guest')->prefix('rider')->name('rider.')->group(function () {
    Route::get('/login', [RiderAuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [RiderAuthenticatedSessionController::class, 'store']);
});

Route::middleware(['auth', 'verified', 'branch', 'password.change_required'])->prefix('rider')->name('rider.')->group(function () {
    Route::get('/', [RiderDashboardController::class, 'index'])->name('dashboard');
    Route::get('/orders', [RiderDashboardController::class, 'data'])->name('orders.data');
    Route::get('/history', [RiderDeliveryHistoryController::class, 'index'])->name('history');
    Route::get('/profile', [RiderProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/logout', [RiderAuthenticatedSessionController::class, 'destroy'])->name('logout');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/branches/select', [BranchSelectionController::class, 'show'])->name('branches.select');
    Route::post('/branches/select', [BranchSelectionController::class, 'store'])->name('branches.select.store');
});

require __DIR__.'/auth.php';
