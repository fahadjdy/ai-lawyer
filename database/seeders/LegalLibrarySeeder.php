<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LegalSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the shared (global) statute reference library. Printable document
 * templates are seeded separately by {@see LegalTemplateSeeder}.
 */
class LegalLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['Indian Penal Code, 1860', '302', 'Punishment for murder', 'Criminal'],
            ['Indian Penal Code, 1860', '420', 'Cheating and dishonestly inducing delivery of property', 'Criminal'],
            ['Code of Civil Procedure, 1908', 'Order VII Rule 11', 'Rejection of plaint', 'Civil'],
            ['Indian Contract Act, 1872', '73', 'Compensation for loss or damage caused by breach of contract', 'Civil'],
            ['Companies Act, 2013', '149', 'Company to have Board of Directors', 'Corporate'],
            ['Constitution of India', 'Article 21', 'Protection of life and personal liberty', 'Constitutional'],
            ['Negotiable Instruments Act, 1881', '138', 'Dishonour of cheque for insufficiency of funds', 'Criminal'],
            ['Hindu Marriage Act, 1955', '13', 'Divorce', 'Family'],
        ];

        foreach ($sections as [$act, $number, $title, $category]) {
            LegalSection::firstOrCreate(
                ['act_name' => $act, 'section_number' => $number],
                ['uuid' => (string) Str::uuid(), 'title' => $title, 'category' => $category],
            );
        }
    }
}
