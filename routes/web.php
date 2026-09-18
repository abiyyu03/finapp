<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\CashAccountController;
use App\Http\Controllers\CashTransactionController;
use App\Http\Controllers\CompanySwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\OpeningBalanceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', function () {
        return view('login');
    })->name('login');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', LogoutController::class)->name('logout');

    // Not behind `active-company` — this is how you get an active company.
    Route::get('/company/select', [CompanySwitchController::class, 'select'])->name('company.select');
    Route::post('/company/{company}/switch', [CompanySwitchController::class, 'switch'])->name('company.switch');
});

Route::middleware(['auth', 'active-company'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // STEP 4 — Chart of Accounts. Every lookup is scoped to the active
    // company inside the controller — see AccountController's docblock.
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');

    // STEP 3 — RBAC.
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');

    // STEP 5 — Journal Entry (draft only; posting is STEP 6).
    Route::get('/journals', [JournalEntryController::class, 'index'])->name('journals.index');
    Route::get('/journals/create', [JournalEntryController::class, 'create'])->name('journals.create');
    Route::post('/journals', [JournalEntryController::class, 'store'])->name('journals.store');
    Route::get('/journals/{journal}', [JournalEntryController::class, 'show'])->name('journals.show');
    Route::get('/journals/{journal}/edit', [JournalEntryController::class, 'edit'])->name('journals.edit');
    Route::put('/journals/{journal}', [JournalEntryController::class, 'update'])->name('journals.update');
    Route::delete('/journals/{journal}', [JournalEntryController::class, 'destroy'])->name('journals.destroy');
    Route::post('/journals/{journal}/post', [JournalEntryController::class, 'post'])->name('journals.post');

    // STEP 7 — Opening Balance. Same journal engine, no separate posting path.
    Route::get('/opening-balance', [OpeningBalanceController::class, 'show'])->name('opening-balance.show');
    Route::post('/opening-balance', [OpeningBalanceController::class, 'store'])->name('opening-balance.store');

    // STEP 8 — Cash Accounts.
    Route::get('/cash-accounts', [CashAccountController::class, 'index'])->name('cash-accounts.index');
    Route::get('/cash-accounts/create', [CashAccountController::class, 'create'])->name('cash-accounts.create');
    Route::post('/cash-accounts', [CashAccountController::class, 'store'])->name('cash-accounts.store');
    Route::get('/cash-accounts/{cashAccount}/edit', [CashAccountController::class, 'edit'])->name('cash-accounts.edit');
    Route::put('/cash-accounts/{cashAccount}', [CashAccountController::class, 'update'])->name('cash-accounts.update');

    // STEP 9 — Bank Accounts.
    Route::get('/bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
    Route::get('/bank-accounts/create', [BankAccountController::class, 'create'])->name('bank-accounts.create');
    Route::post('/bank-accounts', [BankAccountController::class, 'store'])->name('bank-accounts.store');
    Route::get('/bank-accounts/{bankAccount}/edit', [BankAccountController::class, 'edit'])->name('bank-accounts.edit');
    Route::put('/bank-accounts/{bankAccount}', [BankAccountController::class, 'update'])->name('bank-accounts.update');

    // STEP 10/11 — Cash In / Cash Out. Posting reuses JournalPostingService
    // via CashTransactionService — see its docblock.
    Route::get('/cash-transactions', [CashTransactionController::class, 'index'])->name('cash-transactions.index');
    Route::get('/cash-transactions/cash-in/create', [CashTransactionController::class, 'createCashIn'])->name('cash-transactions.create-in');
    Route::post('/cash-transactions/cash-in', [CashTransactionController::class, 'storeCashIn'])->name('cash-transactions.store-in');
    Route::get('/cash-transactions/cash-out/create', [CashTransactionController::class, 'createCashOut'])->name('cash-transactions.create-out');
    Route::post('/cash-transactions/cash-out', [CashTransactionController::class, 'storeCashOut'])->name('cash-transactions.store-out');
    Route::get('/cash-transactions/{transaction}', [CashTransactionController::class, 'show'])->name('cash-transactions.show');
    Route::post('/cash-transactions/{transaction}/post', [CashTransactionController::class, 'post'])->name('cash-transactions.post');
    Route::delete('/cash-transactions/{transaction}', [CashTransactionController::class, 'destroy'])->name('cash-transactions.destroy');

    // STEP 12/13/14 — Reports. Every figure is a DB aggregate over
    // journal_lines/journal_entries, never a separately stored total.
    Route::get('/reports/general-ledger', [ReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');

    // STEP 15 — Audit Log. Read-only; the only writer is AuditLogger::log().
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Every sidebar destination from spec §10 is now a real screen — the
    // STEP 1 placeholder mechanism has nothing left to serve.
});
