<?php
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureMfBillingAccess;
use App\Http\Controllers\Api\V1\{MfBillingController as C, MfBillingWebhookController as W};
Route::prefix('v1/billing')->group(function (): void {
    // Callback público: assinatura HMAC obrigatória no POST, sem conteúdo clínico.
    Route::get('whatsapp/webhook',[W::class,'verify'])->middleware('throttle:60,1');
    Route::post('whatsapp/webhook',[W::class,'receive'])->middleware('throttle:180,1');
    Route::middleware(['auth:sanctum',EnsureMfBillingAccess::class,'throttle:120,1'])->group(function (): void {
        Route::get('settings',[C::class,'settings']);
        Route::get('invoices',[C::class,'index']);
        Route::post('generate',[C::class,'generate']);
        Route::get('students/{student}',[C::class,'account']);
        Route::put('students/{student}',[C::class,'configure']);
        Route::post('invoices/{invoice}/pay',[C::class,'pay']);
        Route::post('invoices/{invoice}/reverse',[C::class,'reverse']);
        Route::post('invoices/{invoice}/cancel',[C::class,'cancel']);
        Route::patch('invoices/{invoice}/terms',[C::class,'terms']);
        Route::get('invoices/{invoice}/preview',[C::class,'preview']);
        Route::post('invoices/{invoice}/reminders',[C::class,'send'])->middleware('throttle:10,1');
        Route::get('invoices/{invoice}/reminders',[C::class,'reminderHistory']);
        Route::post('reminders/{reminder}/resolve',[C::class,'resolveReminder']);
    });
});
