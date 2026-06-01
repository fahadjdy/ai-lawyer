<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A curated quick-reference catalogue of major Indian statutes and their
 * most-cited provisions, powering the Legal Notebook page.
 *
 * This is a study/quick-reference aid — concise paraphrases, NOT the bare act
 * text, and NOT legal advice. Always verify against the official statute.
 */
class LegalLibrary
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalogue(): array
    {
        return [
            [
                'key' => 'bns',
                'name' => 'Bharatiya Nyaya Sanhita, 2023',
                'short' => 'BNS',
                'year' => '2023',
                'category' => 'Criminal',
                'description' => 'The new criminal code that replaced the Indian Penal Code for offences committed on or after 1 July 2024. Section numbers differ from the IPC — always confirm the applicable code by date of offence.',
                'sections' => [
                    ['number' => '101', 'title' => 'Murder', 'summary' => 'Defines murder (corresponds to IPC 300).'],
                    ['number' => '103', 'title' => 'Punishment for murder', 'summary' => 'Death or imprisonment for life, and fine (IPC 302).'],
                    ['number' => '105', 'title' => 'Culpable homicide not amounting to murder', 'summary' => 'Punishment for culpable homicide (IPC 304).'],
                    ['number' => '106', 'title' => 'Death by negligence', 'summary' => 'Causing death by rash or negligent act (IPC 304A).'],
                    ['number' => '109', 'title' => 'Attempt to murder', 'summary' => 'Acts done with intent to cause death (IPC 307).'],
                    ['number' => '115', 'title' => 'Voluntarily causing hurt', 'summary' => 'Punishment for causing hurt (IPC 323).'],
                    ['number' => '63', 'title' => 'Rape', 'summary' => 'Definition of rape (IPC 375).'],
                    ['number' => '64', 'title' => 'Punishment for rape', 'summary' => 'Rigorous imprisonment and fine (IPC 376).'],
                    ['number' => '85', 'title' => 'Cruelty by husband or relatives', 'summary' => 'Cruelty to a woman by husband/relatives (IPC 498A).'],
                    ['number' => '303', 'title' => 'Theft', 'summary' => 'Dishonestly taking movable property (IPC 378/379).'],
                    ['number' => '316', 'title' => 'Criminal breach of trust', 'summary' => 'Dishonest misappropriation of entrusted property (IPC 405/406).'],
                    ['number' => '318', 'title' => 'Cheating', 'summary' => 'Cheating and dishonestly inducing delivery of property (IPC 415/420).'],
                    ['number' => '351', 'title' => 'Criminal intimidation', 'summary' => 'Threatening another with injury (IPC 503/506).'],
                ],
            ],
            [
                'key' => 'ipc',
                'name' => 'Indian Penal Code, 1860',
                'short' => 'IPC',
                'year' => '1860',
                'category' => 'Criminal',
                'description' => 'The principal criminal code of India for offences before 1 July 2024; still relevant for pending and older matters.',
                'sections' => [
                    ['number' => '120B', 'title' => 'Criminal conspiracy', 'summary' => 'Punishment for being party to a criminal conspiracy.'],
                    ['number' => '302', 'title' => 'Punishment for murder', 'summary' => 'Death or life imprisonment, and fine.'],
                    ['number' => '304', 'title' => 'Culpable homicide not amounting to murder', 'summary' => 'Imprisonment up to life or up to 10 years, and fine.'],
                    ['number' => '304A', 'title' => 'Causing death by negligence', 'summary' => 'Death by any rash or negligent act not amounting to culpable homicide.'],
                    ['number' => '304B', 'title' => 'Dowry death', 'summary' => 'Death of a woman within 7 years of marriage linked to dowry cruelty.'],
                    ['number' => '307', 'title' => 'Attempt to murder', 'summary' => 'Acts done with intent/knowledge to cause death.'],
                    ['number' => '323', 'title' => 'Voluntarily causing hurt', 'summary' => 'Up to 1 year imprisonment and/or fine.'],
                    ['number' => '354', 'title' => 'Assault to outrage modesty', 'summary' => 'Assault or criminal force on a woman to outrage her modesty.'],
                    ['number' => '376', 'title' => 'Punishment for rape', 'summary' => 'Rigorous imprisonment of not less than 10 years, up to life.'],
                    ['number' => '379', 'title' => 'Punishment for theft', 'summary' => 'Up to 3 years imprisonment and/or fine.'],
                    ['number' => '392', 'title' => 'Punishment for robbery', 'summary' => 'Rigorous imprisonment up to 10 years and fine.'],
                    ['number' => '406', 'title' => 'Criminal breach of trust', 'summary' => 'Up to 3 years and/or fine.'],
                    ['number' => '420', 'title' => 'Cheating', 'summary' => 'Cheating and dishonestly inducing delivery of property; up to 7 years and fine.'],
                    ['number' => '498A', 'title' => 'Cruelty by husband or relatives', 'summary' => 'Cruelty to a woman by husband/relatives; up to 3 years and fine.'],
                    ['number' => '499', 'title' => 'Defamation', 'summary' => 'Defines defamation by words, signs or visible representations.'],
                    ['number' => '506', 'title' => 'Criminal intimidation', 'summary' => 'Threat of injury to person, reputation or property.'],
                ],
            ],
            [
                'key' => 'crpc',
                'name' => 'Code of Criminal Procedure, 1973',
                'short' => 'CrPC',
                'year' => '1973',
                'category' => 'Criminal',
                'description' => 'Procedural law for criminal cases (succeeded by the Bharatiya Nagarik Suraksha Sanhita, 2023 for new matters).',
                'sections' => [
                    ['number' => '41', 'title' => 'When police may arrest without warrant', 'summary' => 'Circumstances permitting arrest without a warrant.'],
                    ['number' => '154', 'title' => 'Information in cognizable cases (FIR)', 'summary' => 'Registration of the First Information Report.'],
                    ['number' => '161', 'title' => 'Examination of witnesses by police', 'summary' => 'Recording statements during investigation.'],
                    ['number' => '164', 'title' => 'Recording of confessions and statements', 'summary' => 'Statements/confessions recorded by a Magistrate.'],
                    ['number' => '173', 'title' => 'Report of police officer (charge sheet)', 'summary' => 'Final report on completion of investigation.'],
                    ['number' => '436', 'title' => 'Bail in bailable offences', 'summary' => 'Right to bail where the offence is bailable.'],
                    ['number' => '437', 'title' => 'Bail in non-bailable offences', 'summary' => 'Discretionary bail by courts other than High Court/Sessions.'],
                    ['number' => '438', 'title' => 'Anticipatory bail', 'summary' => 'Direction for release on bail in anticipation of arrest.'],
                    ['number' => '482', 'title' => 'Inherent powers of the High Court', 'summary' => 'To prevent abuse of process or secure the ends of justice.'],
                ],
            ],
            [
                'key' => 'cpc',
                'name' => 'Code of Civil Procedure, 1908',
                'short' => 'CPC',
                'year' => '1908',
                'category' => 'Civil',
                'description' => 'Procedural law governing the conduct of civil suits in India.',
                'sections' => [
                    ['number' => 'S. 9', 'title' => 'Courts to try all civil suits', 'summary' => 'Civil courts have jurisdiction unless expressly/impliedly barred.'],
                    ['number' => 'S. 11', 'title' => 'Res judicata', 'summary' => 'Bars re-litigation of matters already finally decided.'],
                    ['number' => 'S. 151', 'title' => 'Inherent powers of court', 'summary' => 'Powers to make orders necessary for the ends of justice.'],
                    ['number' => 'O. VII R. 11', 'title' => 'Rejection of plaint', 'summary' => 'Grounds on which a plaint may be rejected.'],
                    ['number' => 'O. VIII', 'title' => 'Written statement & set-off', 'summary' => 'Defendant\'s pleadings, set-off and counter-claim.'],
                    ['number' => 'O. XXXIX', 'title' => 'Temporary injunctions', 'summary' => 'Grant of interim injunctions and interlocutory orders.'],
                    ['number' => 'S. 100', 'title' => 'Second appeal', 'summary' => 'Appeal to the High Court on a substantial question of law.'],
                ],
            ],
            [
                'key' => 'constitution',
                'name' => 'Constitution of India, 1950',
                'short' => 'Constitution',
                'year' => '1950',
                'category' => 'Constitutional',
                'description' => 'The supreme law of India. Key fundamental rights and writ jurisdiction articles.',
                'sections' => [
                    ['number' => 'Art. 14', 'title' => 'Equality before law', 'summary' => 'Equality before the law and equal protection of the laws.'],
                    ['number' => 'Art. 19', 'title' => 'Protection of certain freedoms', 'summary' => 'Freedom of speech, assembly, association, movement, profession.'],
                    ['number' => 'Art. 21', 'title' => 'Protection of life and personal liberty', 'summary' => 'No deprivation except by procedure established by law.'],
                    ['number' => 'Art. 22', 'title' => 'Protection against arrest and detention', 'summary' => 'Safeguards for arrested persons.'],
                    ['number' => 'Art. 32', 'title' => 'Right to constitutional remedies', 'summary' => 'Right to approach the Supreme Court for enforcement of rights.'],
                    ['number' => 'Art. 226', 'title' => 'Power of High Courts to issue writs', 'summary' => 'Writ jurisdiction for fundamental and other legal rights.'],
                    ['number' => 'Art. 300A', 'title' => 'Right to property', 'summary' => 'No person deprived of property save by authority of law.'],
                ],
            ],
            [
                'key' => 'evidence',
                'name' => 'Indian Evidence Act, 1872',
                'short' => 'Evidence',
                'year' => '1872',
                'category' => 'Procedure',
                'description' => 'Rules of evidence in Indian courts (succeeded by the Bharatiya Sakshya Adhiniyam, 2023 for new matters).',
                'sections' => [
                    ['number' => 'S. 25', 'title' => 'Confession to police not provable', 'summary' => 'No confession made to a police officer can prove guilt.'],
                    ['number' => 'S. 27', 'title' => 'Discovery of facts', 'summary' => 'How much information from an accused may be proved.'],
                    ['number' => 'S. 32', 'title' => 'Dying declaration & statements', 'summary' => 'Statements by persons who cannot be called as witnesses.'],
                    ['number' => 'S. 45', 'title' => 'Opinion of experts', 'summary' => 'Relevance of expert opinion (science, handwriting, etc.).'],
                    ['number' => 'S. 65B', 'title' => 'Admissibility of electronic records', 'summary' => 'Conditions and certificate for electronic evidence.'],
                    ['number' => 'S. 101', 'title' => 'Burden of proof', 'summary' => 'Whoever asserts a fact must prove it exists.'],
                ],
            ],
            [
                'key' => 'contract',
                'name' => 'Indian Contract Act, 1872',
                'short' => 'Contract',
                'year' => '1872',
                'category' => 'Civil',
                'description' => 'Governs the formation and enforcement of contracts in India.',
                'sections' => [
                    ['number' => 'S. 10', 'title' => 'What agreements are contracts', 'summary' => 'Free consent, lawful consideration & object, competent parties.'],
                    ['number' => 'S. 11', 'title' => 'Competency to contract', 'summary' => 'Majority, sound mind, not disqualified by law.'],
                    ['number' => 'S. 14', 'title' => 'Free consent', 'summary' => 'Consent not caused by coercion, undue influence, fraud, etc.'],
                    ['number' => 'S. 23', 'title' => 'Lawful consideration and object', 'summary' => 'Considerations/objects that are unlawful.'],
                    ['number' => 'S. 73', 'title' => 'Compensation for breach', 'summary' => 'Damages arising naturally from a breach of contract.'],
                    ['number' => 'S. 124', 'title' => 'Contract of indemnity', 'summary' => 'Promise to save another from loss.'],
                ],
            ],
            [
                'key' => 'ni-act',
                'name' => 'Negotiable Instruments Act, 1881',
                'short' => 'NI Act',
                'year' => '1881',
                'category' => 'Commercial',
                'description' => 'Law relating to promissory notes, bills of exchange and cheques — including cheque-bounce offences.',
                'sections' => [
                    ['number' => 'S. 138', 'title' => 'Dishonour of cheque', 'summary' => 'Cheque returned unpaid for insufficiency of funds; up to 2 years and/or fine up to twice the amount.'],
                    ['number' => 'S. 139', 'title' => 'Presumption in favour of holder', 'summary' => 'Presumed the cheque was for discharge of a debt/liability.'],
                    ['number' => 'S. 142', 'title' => 'Cognizance of offences', 'summary' => 'Complaint to be filed in writing within the limitation period.'],
                ],
            ],
            [
                'key' => 'it-act',
                'name' => 'Information Technology Act, 2000',
                'short' => 'IT Act',
                'year' => '2000',
                'category' => 'Commercial',
                'description' => 'Law on electronic commerce and cyber offences.',
                'sections' => [
                    ['number' => 'S. 43', 'title' => 'Damage to computer systems', 'summary' => 'Penalty/compensation for unauthorised access or damage.'],
                    ['number' => 'S. 66', 'title' => 'Computer-related offences', 'summary' => 'Dishonest or fraudulent acts under Section 43.'],
                    ['number' => 'S. 66C', 'title' => 'Identity theft', 'summary' => 'Fraudulent use of another\'s electronic signature/password.'],
                    ['number' => 'S. 66D', 'title' => 'Cheating by personation', 'summary' => 'Cheating using a computer resource/communication device.'],
                    ['number' => 'S. 67', 'title' => 'Obscene material in electronic form', 'summary' => 'Publishing/transmitting obscene material electronically.'],
                ],
            ],
            [
                'key' => 'dv-act',
                'name' => 'Protection of Women from Domestic Violence Act, 2005',
                'short' => 'DV Act',
                'year' => '2005',
                'category' => 'Special',
                'description' => 'Civil remedies for women facing domestic violence.',
                'sections' => [
                    ['number' => 'S. 3', 'title' => 'Definition of domestic violence', 'summary' => 'Physical, sexual, verbal, emotional and economic abuse.'],
                    ['number' => 'S. 12', 'title' => 'Application to Magistrate', 'summary' => 'Aggrieved person may seek reliefs from the Magistrate.'],
                    ['number' => 'S. 18', 'title' => 'Protection orders', 'summary' => 'Orders restraining the respondent from acts of violence.'],
                    ['number' => 'S. 19', 'title' => 'Residence orders', 'summary' => 'Right to reside in the shared household.'],
                ],
            ],
            [
                'key' => 'consumer',
                'name' => 'Consumer Protection Act, 2019',
                'short' => 'Consumer',
                'year' => '2019',
                'category' => 'Commercial',
                'description' => 'Protects consumer rights and provides redressal forums.',
                'sections' => [
                    ['number' => 'S. 2(11)', 'title' => 'Deficiency', 'summary' => 'Any fault, imperfection or inadequacy in service.'],
                    ['number' => 'S. 2(47)', 'title' => 'Unfair trade practice', 'summary' => 'Deceptive practices in supply of goods/services.'],
                    ['number' => 'S. 35', 'title' => 'Complaint to District Commission', 'summary' => 'Filing a consumer complaint and pecuniary jurisdiction.'],
                ],
            ],
            [
                'key' => 'mv-act',
                'name' => 'Motor Vehicles Act, 1988',
                'short' => 'MV Act',
                'year' => '1988',
                'category' => 'Special',
                'description' => 'Regulates road transport, licensing and accident compensation.',
                'sections' => [
                    ['number' => 'S. 134', 'title' => 'Duty of driver in case of accident', 'summary' => 'Stop, secure medical aid and report to police.'],
                    ['number' => 'S. 166', 'title' => 'Application for compensation', 'summary' => 'Claim before the Motor Accidents Claims Tribunal.'],
                    ['number' => 'S. 185', 'title' => 'Driving under the influence', 'summary' => 'Penalty for drunk driving / drugs.'],
                ],
            ],
        ];
    }
}
