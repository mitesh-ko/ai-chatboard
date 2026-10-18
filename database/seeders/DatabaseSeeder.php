<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Demo Admin',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        $demoBusiness = Business::firstOrCreate(
            ['slug' => 'acme-cloud-ai'],
            [
                'user_id' => $user->id,
                'name' => 'Acme Cloud AI',
                'website_url' => 'https://example.com',
                'status' => BusinessStatus::Ready,
                'summary' => <<<'MARKDOWN'
### Overview
**Acme Cloud AI** is an enterprise cloud intelligence provider specializing in autonomous AI workflows, real-time customer communication, and website knowledge synthesis.

### Products & Offerings
1. **Cloud Intelligence Agents:** Background agents capable of orchestrating complex web scraping and summarization tasks.
2. **Real-Time Chat Boards:** Zero-latency Socket.io interactive boards for customer engagement.
3. **Knowledge Syncer:** Automated continuous scrapers that keep business information up to date.

### Support & Contact
- **Email:** support@acme-cloud.example.com
- **Operating Hours:** 24/7 Global Support
- **Headquarters:** San Francisco, CA
MARKDOWN,
                'system_prompt' => <<<'PROMPT'
You are the official AI representative for Acme Cloud AI.
Website: https://example.com

OFFICIAL KNOWLEDGE:
- Products: Cloud Intelligence Agents, Real-Time Chat Boards (Socket.io), Knowledge Syncer (automated scrapers).
- Support: support@acme-cloud.example.com
- Hours: 24/7 Global Support
- Location: San Francisco, CA

GUIDELINES:
1. Greet visitors warmly and answer questions concisely using markdown.
2. Never invent pricing or features not listed in your official knowledge.
3. Politely direct unknown questions to support@acme-cloud.example.com.
PROMPT,
                'meta' => [
                    'domain' => 'example.com',
                    'title' => 'Acme Cloud AI — Enterprise Autonomous Cloud',
                    'tagline' => 'Enterprise Autonomous Intelligence & Real-Time AI Chat Boards',
                ],
            ]
        );

        $demoBusiness->scrapedData()->firstOrCreate(
            ['url' => 'https://example.com'],
            [
                'page_title' => 'Acme Cloud AI Homepage',
                'clean_text' => 'Acme Cloud AI provides autonomous AI workflows, automated scraping, and real-time Socket.io chat boards for modern businesses.',
                'scraped_at' => now(),
            ]
        );
    }
}
