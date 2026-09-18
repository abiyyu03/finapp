<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Company;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a standard Chart of Accounts for the given company.
     *
     * @return void
     */
    public function run(Company $company)
    {
        $accounts = [
            // Asset
            ['code' => '1100', 'name' => 'Kas', 'type' => Account::ASSET],
            ['code' => '1200', 'name' => 'Bank', 'type' => Account::ASSET],
            ['code' => '1300', 'name' => 'Piutang Usaha', 'type' => Account::ASSET],
            ['code' => '1400', 'name' => 'Persediaan', 'type' => Account::ASSET],
            ['code' => '1500', 'name' => 'Aset Tetap', 'type' => Account::ASSET],

            // Liability
            ['code' => '2100', 'name' => 'Utang Usaha', 'type' => Account::LIABILITY],
            ['code' => '2200', 'name' => 'Utang Bank', 'type' => Account::LIABILITY],
            ['code' => '2300', 'name' => 'Utang Pajak', 'type' => Account::LIABILITY],

            // Equity
            ['code' => '3100', 'name' => 'Modal Disetor', 'type' => Account::EQUITY],
            ['code' => '3200', 'name' => 'Laba Ditahan', 'type' => Account::EQUITY],
            ['code' => '3300', 'name' => 'Prive', 'type' => Account::EQUITY],

            // Revenue
            ['code' => '4100', 'name' => 'Pendapatan Penjualan', 'type' => Account::REVENUE],
            ['code' => '4200', 'name' => 'Pendapatan Lain-lain', 'type' => Account::REVENUE],

            // Expense
            ['code' => '5100', 'name' => 'Beban Gaji', 'type' => Account::EXPENSE],
            ['code' => '5200', 'name' => 'Beban Sewa', 'type' => Account::EXPENSE],
            ['code' => '5300', 'name' => 'Beban Utilitas', 'type' => Account::EXPENSE],
            ['code' => '5400', 'name' => 'Beban Perlengkapan', 'type' => Account::EXPENSE],
            ['code' => '5500', 'name' => 'Beban Penyusutan', 'type' => Account::EXPENSE],
            ['code' => '5900', 'name' => 'Beban Lain-lain', 'type' => Account::EXPENSE],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'status' => Account::ACTIVE,
                    'is_system' => true,
                ],
            );
        }
    }
}
