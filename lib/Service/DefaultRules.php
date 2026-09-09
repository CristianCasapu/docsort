<?php

declare(strict_types=1);

namespace OCA\DocSort\Service;

/**
 * What a document is, from the words on it. Every kind has a category (its folder), names in
 * English and Romanian (its sub-folder and tag), keywords with weights, and the score it needs.
 * Keywords are matched without diacritics and case, as whole words or phrases; the weights of
 * the keywords found add up (each once). The administrator can change all of this.
 *
 * @return list<array{id:string, category:string, en:string, ro:string, min:int, keywords:array<string,int>}>
 */
final class DefaultRules
{
    public const CATEGORIES = [
        'identity' => ['en' => 'Identity', 'ro' => 'Identitate'],
        'civil' => ['en' => 'Civil status', 'ro' => 'Stare civilă'],
        'permits' => ['en' => 'Permits and licences', 'ro' => 'Permise și licențe'],
        'vehicle' => ['en' => 'Vehicle', 'ro' => 'Auto'],
        'finance' => ['en' => 'Finance', 'ro' => 'Finanțe'],
        'legal' => ['en' => 'Legal', 'ro' => 'Juridic'],
        'property' => ['en' => 'Property', 'ro' => 'Proprietate'],
        'health' => ['en' => 'Health', 'ro' => 'Sănătate'],
        'education' => ['en' => 'Education', 'ro' => 'Educație'],
        'work' => ['en' => 'Work', 'ro' => 'Muncă'],
        'utilities' => ['en' => 'Utilities', 'ro' => 'Utilități'],
        'insurance' => ['en' => 'Insurance', 'ro' => 'Asigurări'],
        'other' => ['en' => 'Other', 'ro' => 'Altele'],
    ];

    /** @return list<array{id:string, category:string, en:string, ro:string, min:int, keywords:array<string,int>}> */
    public static function rules(): array
    {
        $r = static fn (string $id, string $cat, string $en, string $ro, int $min, array $kw) => ['id' => $id, 'category' => $cat, 'en' => $en, 'ro' => $ro, 'min' => $min, 'keywords' => $kw];

        return [
            // identity
            $r('identity_card', 'identity', 'Identity card', 'Carte de identitate', 6, ['carte de identitate' => 6, 'cartea de identitate' => 6, 'identity card' => 6, 'idrou' => 6, 'cnp' => 3, 'seria' => 1, 'cetatenie' => 2, 'domiciliu' => 2, 'nume surname' => 3, 'prenume given names' => 3, 'evidenta populatiei' => 3, 'spclep' => 3, 'cei' => 1]),
            $r('passport', 'identity', 'Passport', 'Pașaport', 6, ['pasaport' => 6, 'passport' => 6, 'p<rou' => 8, 'romania romania' => 1, 'autoritatea emitenta' => 2, 'issuing authority' => 2, 'nationality' => 2, 'cod tara' => 2]),
            $r('residence_permit', 'identity', 'Residence permit', 'Permis de ședere', 6, ['permis de sedere' => 8, 'residence permit' => 8, 'permis de rezidenta' => 8, 'igi' => 2, 'inspectoratul general pentru imigrari' => 4]),
            // civil status
            $r('birth_certificate', 'civil', 'Birth certificate', 'Certificat de naștere', 6, ['certificat de nastere' => 8, 'birth certificate' => 8, 'nascut' => 1, 'nascuta' => 1, 'locul nasterii' => 3, 'numele tatalui' => 3, 'numele mamei' => 3, 'act de nastere' => 6]),
            $r('marriage_certificate', 'civil', 'Marriage certificate', 'Certificat de căsătorie', 6, ['certificat de casatorie' => 8, 'marriage certificate' => 8, 'act de casatorie' => 6, 'sot' => 2, 'sotie' => 2, 'casatoriti' => 3, 'numele dupa casatorie' => 4]),
            $r('death_certificate', 'civil', 'Death certificate', 'Certificat de deces', 6, ['certificat de deces' => 8, 'death certificate' => 8, 'act de deces' => 6, 'decedat' => 3, 'cauza decesului' => 4]),
            $r('divorce', 'civil', 'Divorce', 'Divorț', 6, ['certificat de divort' => 8, 'divort' => 5, 'divorce' => 5, 'desfacerea casatoriei' => 6]),
            // permits and licences
            $r('driving_licence', 'permits', 'Driving licence', 'Permis de conducere', 6, ['permis de conducere' => 8, 'driving licence' => 8, 'driving license' => 8, 'categorii' => 1, 'srpciv' => 4, 'drpciv' => 4, 'am b1 b' => 3]),
            $r('fishing_permit', 'permits', 'Fishing permit', 'Permis de pescuit', 6, ['permis de pescuit' => 8, 'pescuit recreativ' => 6, 'pescuit sportiv' => 6, 'fishing permit' => 8, 'fishing licence' => 8, 'anpa' => 4, 'agentia nationala pentru pescuit' => 6, 'pescar' => 2]),
            $r('hunting_permit', 'permits', 'Hunting permit', 'Permis de vânătoare', 6, ['permis de vanatoare' => 8, 'hunting permit' => 8, 'hunting licence' => 8, 'ajvps' => 5, 'agvps' => 5, 'vanator' => 3, 'fond de vanatoare' => 4, 'autorizatie de vanatoare' => 8]),
            $r('firearm_permit', 'permits', 'Firearm permit', 'Permis de armă', 6, ['permis de arma' => 8, 'firearm' => 5, 'arme si munitii' => 6, 'port arma' => 5, 'detinere arma' => 5]),
            $r('boat_licence', 'permits', 'Boat licence', 'Certificat de conducător de ambarcațiune', 6, ['ambarcatiune' => 5, 'conducator de ambarcatiune' => 8, 'boat licence' => 8, 'skipper' => 4, 'capitania' => 3]),
            $r('building_permit', 'permits', 'Building permit', 'Autorizație de construire', 6, ['autorizatie de construire' => 8, 'building permit' => 8, 'certificat de urbanism' => 8, 'primaria' => 1, 'lucrari de construire' => 4]),
            // vehicle
            $r('vehicle_registration', 'vehicle', 'Vehicle registration', 'Certificat de înmatriculare', 6, ['certificat de inmatriculare' => 8, 'inmatriculare' => 4, 'registration certificate' => 8, 'talon' => 3, 'serie sasiu' => 4, 'vin' => 1, 'numar de inmatriculare' => 4, 'carte de identitate a vehiculului' => 8, 'civ' => 2]),
            $r('vehicle_insurance', 'vehicle', 'Vehicle insurance', 'Asigurare auto', 6, ['rca' => 5, 'raspundere civila auto' => 6, 'casco' => 6, 'polita de asigurare' => 3, 'asigurare auto' => 6, 'motor insurance' => 6, 'carte verde' => 5]),
            $r('vehicle_inspection', 'vehicle', 'Technical inspection', 'Inspecție tehnică (ITP)', 6, ['inspectie tehnica periodica' => 8, 'itp' => 5, 'rar' => 2, 'registrul auto roman' => 5, 'technical inspection' => 8]),
            $r('vehicle_sale', 'vehicle', 'Vehicle sale contract', 'Contract vânzare auto', 7, ['contract de vanzare' => 3, 'autovehicul' => 4, 'vanzator' => 2, 'cumparator' => 2, 'kilometraj' => 3, 'radiere' => 3, 'fiscal auto' => 4]),
            // finance
            $r('invoice', 'finance', 'Invoice', 'Factură', 6, ['factura' => 6, 'factura fiscala' => 8, 'invoice' => 6, 'tva' => 2, 'cui' => 2, 'total de plata' => 3, 'furnizor' => 2, 'cumparator' => 1, 'cota tva' => 3, 'scadenta' => 2, 'proforma' => 3]),
            $r('receipt', 'finance', 'Receipt', 'Chitanță / bon', 6, ['chitanta' => 6, 'bon fiscal' => 8, 'receipt' => 6, 'am primit de la' => 5, 'suma de' => 2, 'casa de marcat' => 4, 'total' => 1]),
            $r('bank_statement', 'finance', 'Bank statement', 'Extras de cont', 6, ['extras de cont' => 8, 'bank statement' => 8, 'iban' => 3, 'sold initial' => 4, 'sold final' => 4, 'rulaj' => 3, 'tranzactii' => 2, 'banca' => 1]),
            $r('tax', 'finance', 'Tax', 'Fisc / ANAF', 6, ['anaf' => 6, 'agentia nationala de administrare fiscala' => 8, 'decizie de impunere' => 8, 'declaratie unica' => 8, 'impozit' => 3, 'contribuabil' => 3, 'directia de taxe' => 6, 'taxe si impozite' => 5, 'tax return' => 6]),
            $r('payslip', 'finance', 'Payslip', 'Fluturaș de salariu', 6, ['fluturas' => 8, 'stat de plata' => 8, 'salariu net' => 6, 'salariu brut' => 6, 'payslip' => 8, 'cas' => 1, 'cass' => 2, 'impozit pe venit' => 3, 'zile lucrate' => 3]),
            // legal
            $r('power_of_attorney', 'legal', 'Power of attorney', 'Procură', 6, ['procura' => 8, 'power of attorney' => 8, 'imputernicesc' => 5, 'mandatar' => 4, 'notar public' => 2]),
            $r('court', 'legal', 'Court', 'Instanță', 6, ['judecatoria' => 5, 'tribunalul' => 5, 'curtea de apel' => 6, 'hotarare' => 3, 'sentinta' => 5, 'citatie' => 6, 'dosar nr' => 4, 'reclamant' => 4, 'parat' => 3, 'instanta' => 3]),
            $r('will', 'legal', 'Will', 'Testament', 7, ['testament' => 8, 'mostenitor' => 4, 'succesiune' => 5, 'certificat de mostenitor' => 8]),
            $r('contract', 'legal', 'Contract', 'Contract', 6, ['contract' => 4, 'contractul' => 3, 'partile' => 2, 'obiectul contractului' => 5, 'durata contractului' => 4, 'prestator' => 2, 'beneficiar' => 2, 'clauze' => 3, 'agreement' => 4, 'semnaturi' => 1]),
            // property
            $r('property_deed', 'property', 'Property deed', 'Act de proprietate', 7, ['contract de vanzare cumparare' => 6, 'imobil' => 4, 'apartament' => 3, 'teren' => 3, 'proprietar' => 2, 'titlu de proprietate' => 8, 'act de proprietate' => 8, 'notar' => 2, 'intabulare' => 5]),
            $r('land_registry', 'property', 'Land registry', 'Carte funciară', 6, ['carte funciara' => 8, 'extras de carte funciara' => 8, 'cadastral' => 5, 'ancpi' => 5, 'ocpi' => 5, 'numar cadastral' => 6]),
            $r('rental', 'property', 'Rental', 'Închiriere', 6, ['contract de inchiriere' => 8, 'chirie' => 5, 'locator' => 4, 'locatar' => 4, 'chirias' => 5, 'lease' => 5, 'rental agreement' => 8]),
            // health
            $r('medical_report', 'health', 'Medical report', 'Acte medicale', 6, ['bilet de iesire' => 8, 'scrisoare medicala' => 8, 'analize' => 4, 'buletin de analize' => 8, 'diagnostic' => 4, 'spital' => 2, 'medic' => 2, 'pacient' => 4, 'medical report' => 8, 'radiografie' => 4, 'ecografie' => 4, 'rmn' => 3]),
            $r('prescription', 'health', 'Prescription', 'Rețetă', 6, ['reteta' => 8, 'prescription' => 8, 'medicament' => 3, 'farmacie' => 3, 'cantitate' => 1, 'rp' => 2, 'compensata' => 4]),
            $r('vaccination', 'health', 'Vaccination', 'Vaccinare', 6, ['vaccin' => 6, 'vaccination' => 6, 'imunizare' => 5, 'schema de vaccinare' => 8, 'carnet de vaccinari' => 8]),
            $r('health_insurance', 'health', 'Health insurance', 'Asigurare de sănătate', 6, ['card de sanatate' => 8, 'cnas' => 6, 'casa de asigurari de sanatate' => 8, 'asigurat' => 2, 'health insurance' => 8, 'adeverinta de asigurat' => 8]),
            // education
            $r('diploma', 'education', 'Diploma', 'Diplomă', 6, ['diploma' => 8, 'diploma de licenta' => 8, 'diploma de bacalaureat' => 8, 'absolvent' => 4, 'universitatea' => 2, 'liceul' => 2, 'titlul de' => 3, 'degree' => 4, 'promotia' => 3]),
            $r('transcript', 'education', 'Transcript', 'Foaie matricolă', 6, ['foaie matricola' => 8, 'transcript of records' => 8, 'situatie scolara' => 6, 'note' => 1, 'media generala' => 4, 'credite' => 3, 'semestrul' => 3]),
            $r('training_certificate', 'education', 'Training certificate', 'Certificat de absolvire', 6, ['certificat de absolvire' => 8, 'certificat de calificare' => 8, 'atestat' => 5, 'curs de' => 3, 'formare profesionala' => 5, 'certificate of completion' => 8, 'a absolvit cursul' => 6, 'ucecom' => 4, 'anc' => 2]),
            // work
            $r('employment_contract', 'work', 'Employment contract', 'Contract de muncă', 7, ['contract individual de munca' => 10, 'employment contract' => 8, 'angajator' => 4, 'angajat' => 3, 'salariat' => 3, 'salariu de baza' => 4, 'revisal' => 4, 'fisa postului' => 5]),
            $r('work_certificate', 'work', 'Work certificate', 'Adeverință de salariat', 6, ['adeverinta de salariat' => 8, 'adeverinta de vechime' => 8, 'adeverinta' => 3, 'se adevereste' => 5, 'este angajat' => 5, 'vechime in munca' => 6, 'carnet de munca' => 8]),
            $r('cv', 'work', 'CV', 'CV', 6, ['curriculum vitae' => 8, 'experienta profesionala' => 5, 'educatie si formare' => 5, 'competente' => 3, 'work experience' => 5, 'skills' => 2, 'europass' => 6]),
            // utilities
            $r('electricity_bill', 'utilities', 'Electricity', 'Energie electrică', 6, ['energie electrica' => 6, 'kwh' => 5, 'electrica' => 3, 'enel' => 4, 'ppc' => 3, 'hidroelectrica' => 5, 'e-on' => 2, 'engie' => 2, 'electricity' => 5, 'consum' => 1, 'index' => 1, 'contor' => 2]),
            $r('gas_bill', 'utilities', 'Gas', 'Gaze', 6, ['gaze naturale' => 8, 'gaz' => 3, 'mwh' => 3, 'engie' => 3, 'e-on' => 2, 'distrigaz' => 4, 'natural gas' => 6]),
            $r('water_bill', 'utilities', 'Water', 'Apă', 6, ['apa canal' => 8, 'apa si canalizare' => 8, 'apa nova' => 6, 'mc apa' => 4, 'water bill' => 6, 'canalizare' => 4]),
            $r('telecom_bill', 'utilities', 'Telecom', 'Telecom', 6, ['orange' => 4, 'vodafone' => 4, 'digi' => 4, 'telekom' => 4, 'rcs' => 3, 'abonament' => 3, 'internet' => 2, 'minute' => 1, 'sms' => 1, 'roaming' => 3, 'telefonie' => 4]),
            // insurance
            $r('insurance_policy', 'insurance', 'Insurance policy', 'Poliță de asigurare', 6, ['polita de asigurare' => 8, 'polita' => 3, 'asigurator' => 4, 'asigurat' => 2, 'prima de asigurare' => 5, 'suma asigurata' => 5, 'insurance policy' => 8, 'pad' => 2, 'asigurare de locuinta' => 8, 'asigurare de viata' => 8, 'asigurare de calatorie' => 8]),
            // other
            $r('warranty', 'other', 'Warranty', 'Garanție', 6, ['certificat de garantie' => 8, 'garantie' => 5, 'warranty' => 8, 'termen de garantie' => 6, 'service' => 1]),
            $r('manual', 'other', 'Manual', 'Manual', 6, ['manual de utilizare' => 8, 'instructiuni de utilizare' => 8, 'user manual' => 8, 'instructions' => 4, 'ghid de instalare' => 6, 'specificatii tehnice' => 5]),
            $r('ticket', 'other', 'Ticket', 'Bilet', 6, ['bilet' => 4, 'boarding pass' => 8, 'ticket' => 5, 'rezervare' => 4, 'booking' => 4, 'check-in' => 3, 'zbor' => 4, 'loc' => 1, 'plecare' => 3, 'sosire' => 3]),
            $r('letter', 'other', 'Letter', 'Corespondență', 5, ['stimate' => 3, 'stimata' => 3, 'cu stima' => 4, 'dear' => 3, 'sincerely' => 4, 'va rugam' => 2, 'in atentia' => 4, 'notificare' => 3, 'instiintare' => 4, 'somatie' => 5]),
        ];
    }
}
