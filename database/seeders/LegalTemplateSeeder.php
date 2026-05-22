<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LegalTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the global library of printable legal documents Indian advocates use
 * most often. Each template is global (available to every firm), fully editable
 * and customizable, and uses {{placeholder}} merge fields the editor detects
 * automatically. All content is in English.
 */
class LegalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            LegalTemplate::withoutGlobalScopes()->updateOrCreate(
                ['slug' => Str::slug($template['title']), 'is_global' => true],
                [
                    'uuid' => (string) Str::uuid(),
                    'team_id' => null,
                    'title' => $template['title'],
                    'category' => $template['category'],
                    'description' => $template['description'],
                    'body' => $this->wrap($template['title'], $template['body']),
                    'variables' => $this->detectVariables($template['body']),
                ],
            );
        }
    }

    /**
     * Wrap a document body in a consistent, print-friendly shell.
     */
    private function wrap(string $title, string $body): string
    {
        return trim($body);
    }

    /**
     * Auto-detect {{placeholder}} merge fields from the body.
     *
     * @return array<int, string>
     */
    private function detectVariables(string $body): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return array<int, array{title:string, category:string, description:string, body:string}>
     */
    private function templates(): array
    {
        return [
            [
                'title' => 'Vakalatnama',
                'category' => 'Court Filings',
                'description' => 'Authority appointing an advocate to act and plead on behalf of a party.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">{{case_title}}<br>Case No. {{case_number}}</p>
<h3 style="text-align:center">VAKALATNAMA</h3>
<p>I/We, <strong>{{client_name}}</strong>, son/daughter/wife of {{client_relation}}, resident of {{client_address}}, the {{party_type}} in the above matter, do hereby appoint and retain <strong>{{advocate_name}}</strong>, Advocate (Enrolment No. {{enrolment_no}}), to appear, act and plead on my/our behalf in the above-noted case.</p>
<p>I/We authorise the said Advocate to do all acts, deeds and things necessary for the conduct of the case, including filing of applications, leading evidence, compromise, withdrawal and engaging other counsel, and I/We agree to ratify the same.</p>
<p style="margin-top:40px">Place: {{place}}<br>Date: {{date}}</p>
<table style="width:100%;margin-top:30px"><tr>
<td>____________________<br>Signature of Client</td>
<td style="text-align:right">____________________<br>Accepted — {{advocate_name}}, Advocate</td>
</tr></table>
HTML,
            ],
            [
                'title' => 'Legal Notice',
                'category' => 'Notices',
                'description' => 'General legal notice demanding action or redress before litigation.',
                'body' => <<<'HTML'
<p style="text-align:right">{{advocate_name}}, Advocate<br>{{advocate_address}}<br>Date: {{date}}</p>
<h3 style="text-align:center">LEGAL NOTICE</h3>
<p><strong>To,</strong><br>{{recipient_name}}<br>{{recipient_address}}</p>
<p>Under instructions from and on behalf of my client, <strong>{{client_name}}</strong>, resident of {{client_address}}, I hereby serve upon you the following legal notice:</p>
<p>1. That {{facts}}.</p>
<p>2. That despite repeated requests you have failed to {{default}}.</p>
<p>3. You are therefore called upon to <strong>{{demand}}</strong> within <strong>{{notice_period}}</strong> days of receipt of this notice, failing which my client shall be constrained to initiate appropriate civil and/or criminal proceedings against you at your sole risk, cost and consequences.</p>
<p>A copy of this notice is retained in my office for record and further necessary action.</p>
<p style="margin-top:30px">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'Cheque Bounce Notice (Section 138 NI Act)',
                'category' => 'Notices',
                'description' => 'Statutory demand notice for dishonour of cheque under Section 138 of the Negotiable Instruments Act, 1881.',
                'body' => <<<'HTML'
<p style="text-align:right">{{advocate_name}}, Advocate<br>Date: {{date}}</p>
<h3 style="text-align:center">NOTICE UNDER SECTION 138 OF THE NEGOTIABLE INSTRUMENTS ACT, 1881</h3>
<p><strong>To,</strong><br>{{recipient_name}}<br>{{recipient_address}}</p>
<p>Under instructions from my client <strong>{{client_name}}</strong>, I state as follows:</p>
<p>1. That you issued cheque bearing No. <strong>{{cheque_number}}</strong> dated {{cheque_date}} for Rs. <strong>{{cheque_amount}}/-</strong> drawn on {{bank_name}}, {{branch}} in discharge of a legally enforceable debt/liability.</p>
<p>2. That the said cheque, on presentation, was returned dishonoured vide bank memo dated {{return_date}} with the remark "<strong>{{return_reason}}</strong>".</p>
<p>3. You are hereby called upon to pay the said sum of Rs. {{cheque_amount}}/- within <strong>15 days</strong> of receipt of this notice, failing which my client shall prosecute you under Section 138 of the Negotiable Instruments Act, 1881, which is punishable with imprisonment up to two years and/or fine up to twice the cheque amount.</p>
<p style="margin-top:30px">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'Bail Application',
                'category' => 'Applications',
                'description' => 'Application seeking regular bail under Section 439 CrPC / BNSS.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">Bail Application No. ______ of {{year}}<br>In FIR No. {{fir_number}}, P.S. {{police_station}}<br>U/s {{sections}}</p>
<p style="text-align:center"><strong>{{applicant_name}}</strong> … Applicant/Accused<br>Versus<br>State of {{state}} … Respondent</p>
<h3 style="text-align:center">APPLICATION FOR GRANT OF BAIL</h3>
<p>MOST RESPECTFULLY SHOWETH:</p>
<p>1. That the applicant has been arrested/is apprehending arrest in connection with the above FIR registered at P.S. {{police_station}}.</p>
<p>2. That the applicant is innocent and has been falsely implicated in the present case.</p>
<p>3. That the applicant is a permanent resident of {{applicant_address}} and there is no likelihood of his absconding or tampering with evidence.</p>
<p>4. That the applicant undertakes to abide by all conditions imposed by this Hon'ble Court and to cooperate with the investigation/trial.</p>
<p><strong>PRAYER:</strong> It is therefore prayed that this Hon'ble Court may be pleased to release the applicant on bail in the interest of justice.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate for the Applicant</p>
HTML,
            ],
            [
                'title' => 'Anticipatory Bail Application',
                'category' => 'Applications',
                'description' => 'Application for pre-arrest bail under Section 438 CrPC / BNSS.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">Anticipatory Bail Application No. ______ of {{year}}<br>In FIR No. {{fir_number}}, P.S. {{police_station}}</p>
<p style="text-align:center"><strong>{{applicant_name}}</strong> … Applicant<br>Versus<br>State of {{state}} … Respondent</p>
<h3 style="text-align:center">APPLICATION FOR ANTICIPATORY BAIL</h3>
<p>MOST RESPECTFULLY SHOWETH:</p>
<p>1. That the applicant apprehends arrest in connection with FIR No. {{fir_number}} registered for offences u/s {{sections}}.</p>
<p>2. That the accusations are false, motivated and made with an oblique motive to harass the applicant.</p>
<p>3. That the applicant is a respectable and permanent resident of {{applicant_address}} with deep roots in society and is ready to join investigation.</p>
<p><strong>PRAYER:</strong> It is prayed that in the event of arrest, the applicant be released on anticipatory bail on such terms as this Hon'ble Court deems fit.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'Plaint (Civil Suit)',
                'category' => 'Court Filings',
                'description' => 'Plaint instituting a civil suit under Order VII CPC.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">Civil Suit No. ______ of {{year}}</p>
<p style="text-align:center"><strong>{{plaintiff_name}}</strong>, resident of {{plaintiff_address}} … Plaintiff<br>Versus<br><strong>{{defendant_name}}</strong>, resident of {{defendant_address}} … Defendant</p>
<h3 style="text-align:center">SUIT FOR {{relief_type}}</h3>
<p>The Plaintiff above-named most respectfully submits as under:</p>
<p>1. That {{para_facts}}.</p>
<p>2. That the cause of action arose on {{cause_date}} at {{jurisdiction}}, within the territorial jurisdiction of this Hon'ble Court.</p>
<p>3. That the value of the suit for the purpose of court fee and jurisdiction is Rs. {{suit_value}}/-, on which the requisite court fee is affixed.</p>
<p><strong>PRAYER:</strong> It is therefore prayed that this Hon'ble Court may be pleased to: (a) {{relief}}; (b) award costs of the suit; and (c) grant any other relief deemed fit.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<table style="width:100%;margin-top:20px"><tr><td>____________________<br>Plaintiff</td><td style="text-align:right">____________________<br>{{advocate_name}}, Advocate</td></tr></table>
HTML,
            ],
            [
                'title' => 'Written Statement',
                'category' => 'Court Filings',
                'description' => 'Defendant\'s written statement / reply to a plaint under Order VIII CPC.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">Civil Suit No. {{case_number}} of {{year}}</p>
<p style="text-align:center">{{plaintiff_name}} … Plaintiff<br>Versus<br>{{defendant_name}} … Defendant</p>
<h3 style="text-align:center">WRITTEN STATEMENT ON BEHALF OF THE DEFENDANT</h3>
<p>The Defendant respectfully submits:</p>
<p><strong>PRELIMINARY OBJECTIONS:</strong></p>
<p>1. That the suit is not maintainable in its present form and is liable to be dismissed.</p>
<p>2. That {{preliminary_objection}}.</p>
<p><strong>REPLY ON MERITS:</strong></p>
<p>3. That the contents of the plaint are denied save those specifically admitted herein. {{reply_on_merits}}</p>
<p><strong>PRAYER:</strong> It is prayed that the suit be dismissed with costs.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate for the Defendant</p>
HTML,
            ],
            [
                'title' => 'General Affidavit',
                'category' => 'Affidavits',
                'description' => 'Standard sworn affidavit/declaration for general use.',
                'body' => <<<'HTML'
<h3 style="text-align:center">AFFIDAVIT</h3>
<p>I, <strong>{{deponent_name}}</strong>, {{relation}} of {{parent_name}}, aged about {{age}} years, resident of {{address}}, do hereby solemnly affirm and declare as under:</p>
<p>1. That I am the deponent herein and am competent to swear this affidavit.</p>
<p>2. That {{statement}}.</p>
<p>3. That the contents of this affidavit are true and correct to my knowledge and belief and nothing material has been concealed therefrom.</p>
<p style="margin-top:40px">Place: {{place}}<br>Date: {{date}}</p>
<p style="text-align:right">____________________<br>DEPONENT</p>
<p style="margin-top:20px"><strong>VERIFICATION:</strong> Verified at {{place}} on this {{date}} that the contents of the above affidavit are true and correct and nothing has been concealed.</p>
<p style="text-align:right">____________________<br>DEPONENT</p>
HTML,
            ],
            [
                'title' => 'Power of Attorney (General)',
                'category' => 'Deeds & Agreements',
                'description' => 'General power of attorney authorising an attorney to act on the principal\'s behalf.',
                'body' => <<<'HTML'
<h3 style="text-align:center">GENERAL POWER OF ATTORNEY</h3>
<p>KNOW ALL MEN BY THESE PRESENTS that I, <strong>{{principal_name}}</strong>, {{principal_relation}}, resident of {{principal_address}} (hereinafter the "Principal"), do hereby nominate, constitute and appoint <strong>{{attorney_name}}</strong>, resident of {{attorney_address}} (hereinafter the "Attorney"), to be my true and lawful attorney to do the following acts:</p>
<p>1. To manage, administer and deal with my property/affairs described as {{subject}}.</p>
<p>2. To sign, execute and present all documents, applications and instruments before any authority, court or office.</p>
<p>3. To appear and represent me before any government, semi-government or judicial authority.</p>
<p>4. To do all such lawful acts as may be necessary for the above purposes, which I hereby agree to ratify.</p>
<p style="margin-top:30px">IN WITNESS WHEREOF I have signed this deed at {{place}} on {{date}}.</p>
<table style="width:100%;margin-top:20px"><tr><td>Witnesses:<br>1. ____________________<br>2. ____________________</td><td style="text-align:right">____________________<br>Principal</td></tr></table>
HTML,
            ],
            [
                'title' => 'Rent / Lease Agreement',
                'category' => 'Deeds & Agreements',
                'description' => 'Residential or commercial rent agreement between landlord and tenant.',
                'body' => <<<'HTML'
<h3 style="text-align:center">RENT AGREEMENT</h3>
<p>This Rent Agreement is made at {{place}} on {{date}} between <strong>{{landlord_name}}</strong>, resident of {{landlord_address}} (hereinafter the "Landlord") and <strong>{{tenant_name}}</strong>, resident of {{tenant_address}} (hereinafter the "Tenant").</p>
<p>WHEREAS the Landlord is the owner of premises situated at <strong>{{property_address}}</strong> and has agreed to let it on rent on the following terms:</p>
<p>1. The tenancy shall be for a period of <strong>{{tenure}}</strong> commencing from {{start_date}}.</p>
<p>2. The monthly rent shall be Rs. <strong>{{rent_amount}}/-</strong> payable on or before the {{due_day}} of each month.</p>
<p>3. The Tenant has paid a refundable security deposit of Rs. {{deposit}}/-.</p>
<p>4. The Tenant shall use the premises for {{purpose}} only and shall not sublet it.</p>
<p>5. The Tenant shall pay electricity and water charges as per actual usage.</p>
<p style="margin-top:30px">IN WITNESS WHEREOF the parties have signed this agreement on the date above written.</p>
<table style="width:100%;margin-top:20px"><tr><td>____________________<br>Landlord</td><td style="text-align:right">____________________<br>Tenant</td></tr></table>
HTML,
            ],
            [
                'title' => 'Non-Disclosure Agreement (NDA)',
                'category' => 'Deeds & Agreements',
                'description' => 'Mutual confidentiality agreement protecting shared confidential information.',
                'body' => <<<'HTML'
<h3 style="text-align:center">NON-DISCLOSURE AGREEMENT</h3>
<p>This Agreement is entered into on {{date}} between <strong>{{party_one}}</strong> and <strong>{{party_two}}</strong> (collectively the "Parties").</p>
<p>1. <strong>Confidential Information</strong> means any non-public information disclosed by one Party to the other, whether written or oral, relating to {{subject_matter}}.</p>
<p>2. The receiving Party shall keep the Confidential Information strictly confidential and use it solely for the purpose of {{purpose}}.</p>
<p>3. The obligations herein shall survive for a period of <strong>{{duration}}</strong> from the date of disclosure.</p>
<p>4. This Agreement shall be governed by the laws of India and subject to the jurisdiction of courts at {{jurisdiction}}.</p>
<p style="margin-top:30px">IN WITNESS WHEREOF the Parties have executed this Agreement.</p>
<table style="width:100%;margin-top:20px"><tr><td>____________________<br>{{party_one}}</td><td style="text-align:right">____________________<br>{{party_two}}</td></tr></table>
HTML,
            ],
            [
                'title' => 'Mutual Consent Divorce Petition',
                'category' => 'Family',
                'description' => 'Joint petition for divorce by mutual consent under Section 13B of the Hindu Marriage Act, 1955.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE FAMILY COURT AT {{court_name}}</h2>
<p style="text-align:center">Petition No. ______ of {{year}} (U/s 13B Hindu Marriage Act, 1955)</p>
<p style="text-align:center"><strong>{{husband_name}}</strong> … Petitioner No. 1<br>and<br><strong>{{wife_name}}</strong> … Petitioner No. 2</p>
<h3 style="text-align:center">PETITION FOR DIVORCE BY MUTUAL CONSENT</h3>
<p>The Petitioners respectfully submit:</p>
<p>1. That the marriage between the Petitioners was solemnised on {{marriage_date}} at {{marriage_place}} according to Hindu rites.</p>
<p>2. That the Petitioners have been living separately since {{separation_date}} and have not been able to live together.</p>
<p>3. That the Petitioners have mutually agreed to dissolve their marriage and have settled all matters relating to {{settlement_terms}}.</p>
<p><strong>PRAYER:</strong> It is prayed that the marriage between the Petitioners be dissolved by a decree of divorce by mutual consent.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<table style="width:100%;margin-top:20px"><tr><td>____________________<br>Petitioner No. 1</td><td style="text-align:right">____________________<br>Petitioner No. 2</td></tr></table>
HTML,
            ],
            [
                'title' => 'Maintenance Application (Section 125 CrPC)',
                'category' => 'Family',
                'description' => 'Application for grant of maintenance under Section 125 of the CrPC / BNSS.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">Maintenance Petition No. ______ of {{year}} (U/s 125 CrPC)</p>
<p style="text-align:center"><strong>{{applicant_name}}</strong> … Applicant<br>Versus<br><strong>{{respondent_name}}</strong> … Respondent</p>
<h3 style="text-align:center">APPLICATION FOR MAINTENANCE</h3>
<p>MOST RESPECTFULLY SHOWETH:</p>
<p>1. That the applicant is the legally wedded wife/dependant of the respondent.</p>
<p>2. That the respondent has neglected and refused to maintain the applicant who has no independent source of income.</p>
<p>3. That the respondent earns approximately Rs. {{respondent_income}}/- per month and is well capable of paying maintenance.</p>
<p><strong>PRAYER:</strong> It is prayed that the respondent be directed to pay maintenance of Rs. <strong>{{maintenance_amount}}/-</strong> per month to the applicant.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'RTI Application',
                'category' => 'Applications',
                'description' => 'Application seeking information under the Right to Information Act, 2005.',
                'body' => <<<'HTML'
<p><strong>To,</strong><br>The Public Information Officer<br>{{public_authority}}<br>{{authority_address}}</p>
<h3 style="text-align:center">APPLICATION UNDER THE RIGHT TO INFORMATION ACT, 2005</h3>
<p>Sir/Madam,</p>
<p>I, <strong>{{applicant_name}}</strong>, resident of {{applicant_address}}, a citizen of India, request the following information under Section 6 of the RTI Act, 2005:</p>
<p>1. {{information_sought}}</p>
<p>2. The period to which the information relates: {{period}}.</p>
<p>I have deposited the requisite application fee of Rs. 10/- vide {{fee_mode}}. I request that the information be provided within the statutory period of 30 days.</p>
<p style="margin-top:30px">Place: {{place}}<br>Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{applicant_name}}</p>
HTML,
            ],
            [
                'title' => 'Consumer Complaint',
                'category' => 'Applications',
                'description' => 'Complaint before the Consumer Disputes Redressal Commission under the Consumer Protection Act, 2019.',
                'body' => <<<'HTML'
<h2 style="text-align:center">BEFORE THE {{forum_name}} CONSUMER DISPUTES REDRESSAL COMMISSION</h2>
<p style="text-align:center">Consumer Complaint No. ______ of {{year}}</p>
<p style="text-align:center"><strong>{{complainant_name}}</strong>, resident of {{complainant_address}} … Complainant<br>Versus<br><strong>{{opposite_party}}</strong>, {{opposite_party_address}} … Opposite Party</p>
<h3 style="text-align:center">COMPLAINT UNDER SECTION 35 OF THE CONSUMER PROTECTION ACT, 2019</h3>
<p>The Complainant respectfully submits:</p>
<p>1. That the Complainant purchased/availed {{goods_or_service}} from the Opposite Party on {{purchase_date}} for a consideration of Rs. {{amount}}/-.</p>
<p>2. That the Opposite Party is guilty of deficiency in service / unfair trade practice in that {{deficiency}}.</p>
<p>3. That the Complainant has suffered loss and mental agony due to the said deficiency.</p>
<p><strong>PRAYER:</strong> It is prayed that the Opposite Party be directed to {{relief}} along with compensation of Rs. {{compensation}}/- and costs.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'Reply to Legal Notice',
                'category' => 'Notices',
                'description' => 'Formal reply rebutting the allegations in a received legal notice.',
                'body' => <<<'HTML'
<p style="text-align:right">{{advocate_name}}, Advocate<br>Date: {{date}}</p>
<h3 style="text-align:center">REPLY TO LEGAL NOTICE DATED {{notice_date}}</h3>
<p><strong>To,</strong><br>{{recipient_name}}<br>{{recipient_address}}</p>
<p>Under instructions from my client <strong>{{client_name}}</strong>, I reply to your notice dated {{notice_date}} as follows:</p>
<p>1. That the contents of your notice are false, baseless and denied in their entirety except those that are matters of record.</p>
<p>2. That the true facts are that {{true_facts}}.</p>
<p>3. That your notice is misconceived and appears to be an attempt to pressurise and harass my client.</p>
<p>4. My client reserves the right to take appropriate legal action against you for issuing a false and frivolous notice.</p>
<p style="margin-top:30px">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'Adjournment Application',
                'category' => 'Court Filings',
                'description' => 'Application seeking adjournment of a hearing for sufficient cause.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">{{case_title}}<br>Case No. {{case_number}}</p>
<h3 style="text-align:center">APPLICATION FOR ADJOURNMENT</h3>
<p>MOST RESPECTFULLY SHOWETH:</p>
<p>1. That the above matter is listed for {{purpose}} on {{hearing_date}}.</p>
<p>2. That {{reason}}, due to which the applicant is unable to proceed today.</p>
<p>3. That the present application is bona fide and not intended to cause any delay.</p>
<p><strong>PRAYER:</strong> It is prayed that the matter be adjourned to a date convenient to this Hon'ble Court.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate</p>
HTML,
            ],
            [
                'title' => 'Last Will and Testament',
                'category' => 'Deeds & Agreements',
                'description' => 'Will setting out the distribution of a testator\'s estate.',
                'body' => <<<'HTML'
<h3 style="text-align:center">LAST WILL AND TESTAMENT</h3>
<p>I, <strong>{{testator_name}}</strong>, {{testator_relation}}, resident of {{testator_address}}, aged about {{age}} years, being of sound mind and memory, do hereby make this my last Will and Testament, revoking all earlier wills and codicils.</p>
<p>1. That I am making this Will out of my own free will, without any coercion or undue influence.</p>
<p>2. That I bequeath my movable and immovable properties described as {{property_description}} to {{beneficiaries}} in the manner set out below: {{distribution}}.</p>
<p>3. That I appoint <strong>{{executor_name}}</strong> as the Executor of this Will.</p>
<p style="margin-top:30px">IN WITNESS WHEREOF I have signed this Will at {{place}} on {{date}} in the presence of the witnesses below.</p>
<table style="width:100%;margin-top:20px"><tr><td>Witnesses:<br>1. ____________________<br>2. ____________________</td><td style="text-align:right">____________________<br>Testator</td></tr></table>
HTML,
            ],
            [
                'title' => 'Caveat Petition',
                'category' => 'Court Filings',
                'description' => 'Caveat under Section 148A CPC to ensure notice before any order is passed.',
                'body' => <<<'HTML'
<h2 style="text-align:center">IN THE COURT OF {{court_name}}</h2>
<p style="text-align:center">Caveat Petition No. ______ of {{year}} (U/s 148A CPC)</p>
<p style="text-align:center"><strong>{{caveator_name}}</strong> … Caveator<br>Versus<br><strong>{{expected_party}}</strong> … Expected Petitioner</p>
<h3 style="text-align:center">CAVEAT PETITION</h3>
<p>The Caveator respectfully submits:</p>
<p>1. That the above-named expected party is likely to file a petition/application concerning {{subject_matter}}.</p>
<p>2. That the Caveator has an interest in the matter and is entitled to be heard before any order is passed.</p>
<p><strong>PRAYER:</strong> It is prayed that no order be passed without notice to the Caveator. A copy of this caveat is being served on the expected party.</p>
<p style="margin-top:30px">Place: {{place}} &nbsp;&nbsp; Date: {{date}}</p>
<p style="text-align:right">____________________<br>{{advocate_name}}, Advocate for the Caveator</p>
HTML,
            ],
            [
                'title' => 'Indemnity Bond',
                'category' => 'Affidavits',
                'description' => 'Bond indemnifying a party against loss arising from a stated matter.',
                'body' => <<<'HTML'
<h3 style="text-align:center">INDEMNITY BOND</h3>
<p>I, <strong>{{executant_name}}</strong>, {{executant_relation}}, resident of {{executant_address}}, do hereby execute this Indemnity Bond in favour of <strong>{{beneficiary_name}}</strong>:</p>
<p>1. That {{facts}}.</p>
<p>2. That I hereby undertake to indemnify and keep indemnified the said {{beneficiary_name}} against any loss, damage, claim or liability arising in respect of {{subject_matter}}.</p>
<p>3. That this bond is executed voluntarily and the contents are true and correct.</p>
<p style="margin-top:40px">Place: {{place}}<br>Date: {{date}}</p>
<table style="width:100%;margin-top:20px"><tr><td>Witnesses:<br>1. ____________________<br>2. ____________________</td><td style="text-align:right">____________________<br>Executant</td></tr></table>
HTML,
            ],
        ];
    }
}
