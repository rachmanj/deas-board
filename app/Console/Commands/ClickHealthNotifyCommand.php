<?php

namespace App\Console\Commands;

use App\Support\BoardHealth;
use App\Support\HealthDeviationNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;

class ClickHealthNotifyCommand extends Command
{
    protected $signature = 'click:health-notify
                            {--dry-run : Tampilkan pesan tanpa mengirim}
                            {--force : Abaikan catatan sudah dinotifikasi hari ini}
                            {--limit=50 : Batas task baru per eksekusi}
                            {--days=7 : Ambang hari untuk kategori done tanpa bukti}';

    protected $description = 'Kirim notifikasi Telegram untuk penyimpangan kesehatan papan';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $limit = 50;
        }

        $token = Env::get('TELEGRAM_BOT_TOKEN');
        $chatId = Env::get('TELEGRAM_CHAT_ID');
        $token = is_string($token) ? trim($token) : '';
        $chatId = is_string($chatId) ? trim($chatId) : '';

        $notifier = new HealthDeviationNotifier(
            $token !== '' ? $token : null,
            $chatId !== '' ? $chatId : null,
        );

        $days = BoardHealth::resolveDoneWithoutEvidenceDays((int) $this->option('days'));

        $plans = $notifier->pendingNotifications(now(), $force, $limit, $days);

        if ($dryRun) {
            foreach ($plans as $plan) {
                $this->line('---');
                $this->line($plan['message']);
            }

            return self::SUCCESS;
        }

        if ($token === '' || $chatId === '') {
            Log::info('notifikasi dilewati (env belum diisi)');

            return self::SUCCESS;
        }

        if ($plans === []) {
            return self::SUCCESS;
        }

        $notifier->sendAndRecord($plans, now(), $force);

        return self::SUCCESS;
    }
}
