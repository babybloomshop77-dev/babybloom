<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetAdminPassword extends Command
{
    protected $signature = 'admin:password {email=admin@babybloom.am}';

    protected $description = 'Сменить пароль администратора (и разлогинить все устройства)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Пользователь не найден: '.$this->argument('email'));

            return self::FAILURE;
        }

        $password = (string) $this->secret('Новый пароль (не меньше 12 символов)');

        if (mb_strlen($password) < 12) {
            $this->error('Пароль слишком короткий.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Повторите пароль')) {
            $this->error('Пароли не совпадают.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->info('Готово: пароль изменён.');

        return self::SUCCESS;
    }
}
