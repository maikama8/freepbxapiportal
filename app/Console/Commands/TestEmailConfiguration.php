<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Email\EmailService;

class TestEmailConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test {email : The email address to send test email to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test email configuration by sending a test email';

    protected EmailService $emailService;

    /**
     * Create a new command instance.
     */
    public function __construct(EmailService $emailService)
    {
        parent::__construct();
        $this->emailService = $emailService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $this->info("Testing email configuration...");
        $this->info("Sending test email to: {$email}");
        
        if ($this->emailService->testConfiguration($email)) {
            $this->info("✅ Test email sent successfully!");
            $this->info("Please check your inbox and spam folder.");
        } else {
            $this->error("❌ Failed to send test email.");
            $this->error("Please check your email configuration and logs.");
        }
        
        return 0;
    }
}
