<?php

use App\Http\Controllers\BroadcastingAuthProxyController;
use App\Http\Controllers\FirebaseWebConfigController;
use App\Http\Controllers\Pages\AiAdviserController;
use App\Http\Controllers\Pages\ChatController;
use App\Http\Controllers\Pages\ContactController;
use App\Http\Controllers\Pages\DashboardController;
use App\Http\Controllers\Pages\GoogleWorkspaceController;
use App\Http\Controllers\Pages\GoogleWorkspacePagesController;
use App\Http\Controllers\Pages\HomeController;
use App\Http\Controllers\Pages\LeadController;
use App\Http\Controllers\Pages\MarketplaceController;
use App\Http\Controllers\Pages\NotesController;
use App\Http\Controllers\Pages\NotificationsController;
use App\Http\Controllers\Pages\PaymentsController;
use App\Http\Controllers\Pages\PortfolioController;
use App\Http\Controllers\Pages\ProfileController;
use App\Http\Controllers\Pages\SubscriptionsController;
use App\Http\Controllers\Pages\StudyRequirementsController;
use App\Http\Controllers\Pages\SupportTicketDetailsController;
use App\Http\Controllers\Pages\SupportTicketsCreateController;
use App\Http\Controllers\Pages\SupportTicketsExistingController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);

Route::get('/dashboard', DashboardController::class)->name('dashboard');
Route::get('/ai-adviser', AiAdviserController::class)->name('ai-adviser');
Route::get('/leads', LeadController::class)->name('leads');
Route::get('/google-workspace', GoogleWorkspaceController::class)->name('google-workspace');
Route::get('/google-workspace/overview', [GoogleWorkspacePagesController::class, 'overview'])->name('google-workspace.overview');
Route::get('/google-workspace/calendar', [GoogleWorkspacePagesController::class, 'calendar'])->name('google-workspace.calendar');
Route::get('/google-workspace/drive', [GoogleWorkspacePagesController::class, 'drive'])->name('google-workspace.drive');
Route::get('/google-workspace/youtube', [GoogleWorkspacePagesController::class, 'youtube'])->name('google-workspace.youtube');

Route::get('/notifications', NotificationsController::class)->name('notifications');
Route::get('/payments', PaymentsController::class)->name('payments');
Route::get('/notes', NotesController::class)->name('notes');
Route::get('/marketplace', MarketplaceController::class)->name('marketplace');
Route::get('/subscriptions', SubscriptionsController::class)->name('subscriptions');
Route::get('/study-requirements', StudyRequirementsController::class)->name('study-requirements');

Route::get('/profile', ProfileController::class)->name('profile');
Route::get('/portfolio', PortfolioController::class)->name('portfolio');
Route::get('/portfolio/create', [PortfolioController::class, 'create'])->name('portfolio.create');

Route::get('/support-tickets', SupportTicketsExistingController::class)->name('support-tickets');

Route::get('/support-tickets/create', SupportTicketsCreateController::class)->name('support-tickets-create');

Route::get('/support-tickets/{supportTicket}', SupportTicketDetailsController::class)->name('support-ticket-details');

Route::get('/contact', ContactController::class)->name('contact');

Route::get('/chat/{conversation?}', ChatController::class)->where(['conversation' => '[0-9]+'])->name('chat');

Route::get('/firebase/web-config', FirebaseWebConfigController::class);
Route::post('/broadcasting/auth', BroadcastingAuthProxyController::class);

require __DIR__.'/auth.php';
