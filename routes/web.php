<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\BranchImageController;
use App\Http\Controllers\BranchSelectionController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryHeroImageController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\Auth\AuthenticatedSessionController as CustomerAuthenticatedSessionController;
use App\Http\Controllers\Customer\Auth\RegisteredUserController as CustomerRegisteredUserController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemImageController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OrderActionController;
use App\Http\Controllers\OrderDashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShiftController;
use App\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/menu', [MenuController::class, 'index'])->name('menu.index');
Route::get('/menu/{menuItem:slug}', [MenuController::class, 'show'])->name('menu.show');

Route::get('/branches', [BranchesController::class, 'index'])->name('branches.index');
Route::get('/branches/{branch}/select', [BranchesController::class, 'select'])->name('branches.pick');

Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'submit'])->name('contact.submit');
Route::get('/about', [AboutController::class, 'index'])->name('about');

Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

Route::get('/cart', [CartController::class, 'show'])->name('cart.show');
Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/{line}', [CartController::class, 'updateQuantity'])->name('cart.update');
Route::delete('/cart/{line}', [CartController::class, 'remove'])->name('cart.remove');

Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/track', [TrackingController::class, 'lookup'])->name('tracking.lookup');
Route::post('/track/find', [TrackingController::class, 'find'])->name('tracking.find');
Route::get('/track/{order:track_token}', [TrackingController::class, 'show'])->name('tracking.show');
Route::get('/track/{order:track_token}/data', [TrackingController::class, 'data'])->name('tracking.data');

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

Route::middleware(['auth', 'verified', 'branch'])->group(function () {
    Route::get('/dashboard', [OrderDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/orders', [OrderDashboardController::class, 'data'])->name('dashboard.orders.data');

    Route::post('/dashboard/orders/{order}/accept', [OrderActionController::class, 'accept'])->name('orders.accept');
    Route::post('/dashboard/orders/{order}/reject', [OrderActionController::class, 'reject'])->name('orders.reject');
    Route::post('/dashboard/orders/{order}/advance', [OrderActionController::class, 'advance'])->name('orders.advance');
    Route::post('/dashboard/orders/{order}/cancel', [OrderActionController::class, 'cancel'])->name('orders.cancel');

    Route::get('/dashboard/shift', [ShiftController::class, 'show'])->name('shift.show');
    Route::post('/dashboard/shift/start', [ShiftController::class, 'start'])->name('shift.start');
    Route::post('/dashboard/shift/end', [ShiftController::class, 'end'])->name('shift.end');

    Route::get('/dashboard/branches', [BranchImageController::class, 'index'])->name('dashboard.branches.index');
    Route::post('/dashboard/branches/{branch}/image', [BranchImageController::class, 'update'])->name('dashboard.branches.image.update');
    Route::delete('/dashboard/branches/{branch}/image', [BranchImageController::class, 'destroy'])->name('dashboard.branches.image.destroy');

    Route::get('/dashboard/menu-items', [MenuItemImageController::class, 'index'])->name('dashboard.menu-items.index');
    Route::post('/dashboard/menu-items/{menuItem}/image', [MenuItemImageController::class, 'update'])->name('dashboard.menu-items.image.update');
    Route::delete('/dashboard/menu-items/{menuItem}/image', [MenuItemImageController::class, 'destroy'])->name('dashboard.menu-items.image.destroy');

    Route::get('/dashboard/hero-images', [CategoryHeroImageController::class, 'index'])->name('dashboard.hero-images.index');
    Route::post('/dashboard/hero-images/{category}/image', [CategoryHeroImageController::class, 'update'])->name('dashboard.hero-images.image.update');
    Route::delete('/dashboard/hero-images/{category}/image', [CategoryHeroImageController::class, 'destroy'])->name('dashboard.hero-images.image.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/branches/select', [BranchSelectionController::class, 'show'])->name('branches.select');
    Route::post('/branches/select', [BranchSelectionController::class, 'store'])->name('branches.select.store');
});

require __DIR__.'/auth.php';
