<?php
/**
 * Import "Library Stock_4.xlsx" into library_books.
 *
 * Source workbook: Library Stock_4.xlsx (Sheet1) -- 293 title rows expanded
 * into 561 individual copies (one library_books row per accession number).
 *
 * Usage (CLI):
 *   php import_library_stock_4.php install [CENTRE_ID]
 *   php import_library_stock_4.php verify  [CENTRE_ID]
 *   php import_library_stock_4.php rollback [CENTRE_ID]
 *
 * Usage (Admin panel -> DB Migrations -> Run):
 *   The panel cannot pass a centre id, so set $FORCE_CENTRE_ID below first
 *   (unless there is exactly one active centre, which is auto-selected).
 *
 * Centre resolution order:
 *   1) CENTRE_ID passed as CLI argument
 *   2) $FORCE_CENTRE_ID variable below (set it if you want to hard-code)
 *   3) If exactly one active centre exists, that one is used automatically
 *   4) Otherwise the script lists active centres and stops so you can pick one.
 *
 * Every inserted row is tagged with created_by = 'migration:library_stock_4'
 * so it can be safely re-run (idempotent) and rolled back.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/library_helper.php';

// Centre these books belong to: NIELIT Bhubaneswar (id=1, BBSR).
$FORCE_CENTRE_ID = 1;

const IMPORT_TAG = 'migration:library_stock_4';

function out($m) {
    if (php_sapi_name() === 'cli') { echo $m . PHP_EOL; }
    else { echo htmlspecialchars($m) . "<br>\n"; }
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    out('Database connection is not available.');
    return;
}

function resolveCentreId(mysqli $conn, int $forced): int
{
    if ($forced > 0) { return $forced; }
    $res = $conn->query("SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY id ASC");
    $centres = [];
    if ($res) { while ($row = $res->fetch_assoc()) { $centres[] = $row; } }
    if (count($centres) === 1) {
        out("Auto-selected the only active centre: #" . $centres[0]['id'] . ' ' . $centres[0]['name']);
        return (int) $centres[0]['id'];
    }
    out("No centre id supplied. Active centres:");
    foreach ($centres as $c) {
        out("  id=" . $c['id'] . "  code=" . ($c['code'] ?? '') . "  name=" . $c['name']);
    }
    out("Re-run with the desired centre id, e.g.:  php import_library_stock_4.php install <CENTRE_ID>");
    return 0;
}

function books(): array
{
    return [
    ['title'=>'WEB DESIGNING AND PUBLISHING(M2-R5)','author'=>'P.K PANDAY','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'156','accs'=>['01','02','03','04','05','06','07','08','09','10']],
    ['title'=>'WEB DESIGNING AND PUBLISHING(M2-R5)','author'=>'SATISH JAIN,M GEETHA','publisher'=>'BPB PUBLICATION','edition'=>'2ND','purchase_date'=>'2022-06-15','price'=>'330','accs'=>['11','12','13','14','15','16','17','18','19','20']],
    ['title'=>'HTML 5 and CSS3 Made Easy(CD ROM Included)','author'=>'IVAN BAY ROSE','publisher'=>'BPB PUBLICATION','edition'=>'1st','purchase_date'=>'2022-06-15','price'=>'498','accs'=>['21','22']],
    ['title'=>'Mastering HTML,CSS & Javascript Web Publishing','author'=>'Laura Lemay, Rafe Colburn, Jennifer Kyrnin','publisher'=>'BPB PUBLICATION','edition'=>'1st','purchase_date'=>'2022-06-15','price'=>'540','accs'=>['23','24']],
    ['title'=>'HTML 5 covers CSS3, Javascript, PHP, Java, JSP, XML and AJAX(CD ROM)','author'=>'Kogent Learning Solutions Inc','publisher'=>'Dreamtec Press','edition'=>'INDIAN EDITION','purchase_date'=>'2022-06-13','price'=>'479','accs'=>['25','26']],
    ['title'=>'Java the complete reference','author'=>'Herbert Schildt','publisher'=>'MC Graw Hill Education','edition'=>'10th','purchase_date'=>'2022-06-13','price'=>'708','accs'=>['27','28']],
    ['title'=>'The Complete References C++','author'=>'Herbert Schildt','publisher'=>'MC graw hill education','edition'=>'4th','purchase_date'=>'2022-06-13','price'=>'580','accs'=>['29','30']],
    ['title'=>'Object Oriented Programming with C++','author'=>'Reema Thareja','publisher'=>'OXFORD University Press','edition'=>'1st','purchase_date'=>'2022-06-13','price'=>'420','accs'=>['31']],
    ['title'=>'Programming & Problem Solving through Python','author'=>'SATISH JAIN, SASHI SINGH','publisher'=>'BPB PUBLICATION','edition'=>'1st','purchase_date'=>'2022-06-15','price'=>'396','accs'=>['32','33','34','35','36','37','38','39','40','41']],
    ['title'=>'PYTHON PROGRAMMING','author'=>'P.K PANDEY','publisher'=>'T BALAJI PUBLICATION','edition'=>'2ND','purchase_date'=>'2022-05-07','price'=>'156','accs'=>['42','43','44','45','46','47','48','49','50','51']],
    ['title'=>'DATA ANALYTICS WITH GOOGLE CLOUD PLATFORM','author'=>'MURARI RAMUKA','publisher'=>'BPB PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'699','accs'=>['52','53']],
    ['title'=>'STATISTICS OF MACHINE LEARNING','author'=>'HIMANSHU SINGH','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'949','accs'=>['54','55']],
    ['title'=>'PRAGMATIC MACHINE LEARNING WITH PHTHON','author'=>'AVISHEK NAG','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'849','accs'=>['56','57']],
    ['title'=>'MACHINE LEARNING','author'=>'DR. RUSHI DOSHI ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'599','accs'=>['58','59']],
    ['title'=>'DATA SCIENCE FUNDAMENTALS AND PRATICAL APPROACHES','author'=>'DR. GYPSY NANDI ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'849','accs'=>['60','61']],
    ['title'=>'MACHINE LEARNING FOR BEGINNERS','author'=>'HARSH BHASIN','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'749','accs'=>['62','63']],
    ['title'=>'MACHINE LEARNING COOKBOOK FOR PYTHON','author'=>'REHAN GUHA','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'499','accs'=>['64','65']],
    ['title'=>'DATA ANALYTICS : PRINCIPLES, TOOLS AND PRATICES','author'=>'DR. GAUARORAA','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'599','accs'=>['66','67']],
    ['title'=>'DATA SCIENCE FOR BUSINESS PROFESSIONALS','author'=>'','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'949','accs'=>['68','69']],
    ['title'=>'MACHINE LEARNING WITH PYTHON','author'=>'ABHISHEK VIJAYVARGIA','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'499','accs'=>['70','71']],
    ['title'=>'CORE PYTHON PROGRAMMING','author'=>'DR. R. NAGESWARA ROA','publisher'=>'Dreamtec Press','edition'=>'3RD','purchase_date'=>'2022-06-13','price'=>'699','accs'=>['72']],
    ['title'=>'PYTHON FOR DATA SCIENCE','author'=>'JOHN PAUL MUELLER ET AL','publisher'=>'JOHN WILEY & SONS','edition'=>'2ND','purchase_date'=>'2022-06-14','price'=>'729','accs'=>['73']],
    ['title'=>'The Complete References PHTHON','author'=>'MARTIN C. BROWN','publisher'=>'MC Graw Hill Education','edition'=>'11TH','purchase_date'=>'2022-06-14','price'=>'825','accs'=>['74']],
    ['title'=>'MACHINE LEARNING USING PYTHON','author'=>'MANARANJAN PRADHAN ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'4TH','purchase_date'=>'2022-06-13','price'=>'519','accs'=>['75']],
    ['title'=>'PYTHON PROGRAMMING A MODULAR APPROACH','author'=>'SHEETAL TANEJA ET AL','publisher'=>'PEARSON','edition'=>'4TH','purchase_date'=>'2022-06-13','price'=>'519','accs'=>['76']],
    ['title'=>'IT TOOLS AND NETWORK BASICS','author'=>'NAVEEN TANEJA ET AL','publisher'=>'T BALAJI PUBLICATION','edition'=>'4TH','purchase_date'=>'2022-06-13','price'=>'519','accs'=>['77','78','79','80','81','82','83','84','85','86']],
    ['title'=>'IT TOOLS AND NETWORK BASICS','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'396','accs'=>['87','88','89','90','91','92','93','94','95','96']],
    ['title'=>'DATA COMMUNICATIONS AND NETWORKING','author'=>'BEHROUZ A. FOROUZAN','publisher'=>'MC Graw Hill Education','edition'=>'5TH','purchase_date'=>'2022-06-13','price'=>'620','accs'=>['97','98']],
    ['title'=>'BASICS ELECTRONICS SOLID STATE','author'=>'B.L THERAJA','publisher'=>'S CHAND','edition'=>'1ST','purchase_date'=>'2022-06-13','price'=>'550','accs'=>['99']],
    ['title'=>'ELECTRONICS DEVICES AND CIRCUIT THEORY','author'=>'ROBERT L. BOYLESTAD','publisher'=>'PEARSON','edition'=>'11TH','purchase_date'=>'2022-06-13','price'=>'727','accs'=>['100']],
    ['title'=>'A TEXTBOOK OF ELECTRONICS CIRCUIT','author'=>'DR. R.S SEDHA','publisher'=>'S CHAND','edition'=>'1ST','purchase_date'=>'2022-06-14','price'=>'450','accs'=>['101']],
    ['title'=>'FUNDAMENTALA OF DIGITAL CIRCUITS','author'=>'A. ANAND KUMAR','publisher'=>'PHI','edition'=>'4TH','purchase_date'=>'2022-06-13','price'=>'440','accs'=>['102']],
    ['title'=>'BASIC ELECTRONICS 2E','author'=>'D.P KOTHARI ET AL','publisher'=>'MC Graw Hill Education','edition'=>'2ND','purchase_date'=>'2022-06-14','price'=>'540','accs'=>['103']],
    ['title'=>'COMPUTER NETWORKS','author'=>'ANDRWA S. TANENBAUM ET AL','publisher'=>'PEARSON','edition'=>'5TH','purchase_date'=>'2022-06-14','price'=>'789','accs'=>['104']],
    ['title'=>'MICROELECTRONICS CIRCUITS','author'=>'ADEL S. SEDRA','publisher'=>'OXFORD University Press','edition'=>'6TH','purchase_date'=>'2022-06-13','price'=>'580','accs'=>['105']],
    ['title'=>'WIRELESS COMMUNICATIONS','author'=>'THEODORE S. RAPPAPORT','publisher'=>'PEARSON','edition'=>'2ND','purchase_date'=>'2022-06-13','price'=>'663','accs'=>['106']],
    ['title'=>'COMPUTER NETWORKS A TOP-DOWN APPROACH','author'=>'BEHROUZ A FOROZAN ET AL','publisher'=>'MC Graw Hill Education','edition'=>'SPECIAL INDIAN EDITION','purchase_date'=>'2022-06-13','price'=>'584','accs'=>['107']],
    ['title'=>'ELECTRONICS FUNDAMENTALS AND APPLICATIONS','author'=>'D. CHATTOPADHYAY ET AL','publisher'=>'NEW AGE INTERNATIONAL PUBLISHERS','edition'=>'12TH','purchase_date'=>'2022-06-13','price'=>'316','accs'=>['108']],
    ['title'=>'INTRODUCTION TO INTERNET OF THINGS AND ITS APPLICATIONS','author'=>'P.K PANDEY ET AL','publisher'=>'T BALAJI PUBLICATION','edition'=>'2ND','purchase_date'=>'2022-05-07','price'=>'156','accs'=>['109','110','111','112','113','114','115','116','117','118']],
    ['title'=>'IOT AND APPLICATIONS','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'360','accs'=>['119','120','121','122','123','124','125','126','127','128']],
    ['title'=>'IOTS A HANDS-ON APPROACH','author'=>'ARSHDEEP BAHGA','publisher'=>'UNIVERSITIES PRESS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'660','accs'=>['129','130']],
    ['title'=>'IOT BASED PROJECTS','author'=>'DR. RAJESH SINGH ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'498','accs'=>['131','132']],
    ['title'=>'IOTS WITH ARDUINO AND BOLT','author'=>'ASHWIN PAJANKAR','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'498','accs'=>['133','134']],
    ['title'=>'21 EXPERIMENTS ON IOT','author'=>'YASHAVANT KANETKAR','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'450','accs'=>['135','136']],
    ['title'=>'THE INTERNET OF THINGS KEY APPLICATIONS AND PROTOCOLS','author'=>'OLIVIER HERSENT','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'1ST','purchase_date'=>'2022-06-14','price'=>'599','accs'=>['137','138']],
    ['title'=>'IOT FOR BEGINNERS','author'=>'VIBHA SONI','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'599','accs'=>['139','140']],
    ['title'=>'IOT PRINCIPLES, PARADIGMS AND APPLICATIONS OF IOT','author'=>'DR. KAMLESH LAKHWANI','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'498','accs'=>['141','142']],
    ['title'=>'ADVANCED NETWORKS NETWORKING PERIPHERAL AND OPERATING SYSTEM SOFTWAR4E & TOOLS','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'450','accs'=>['143','144','145','146','147']],
    ['title'=>'PC HARDWARE & COMPONENTS AND PC ARCHITECTURE','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'450','accs'=>['148','149','150','151','152']],
    ['title'=>'PERSONALITY DEVELOPMENT AND DEVICES & APPLICATIONS','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'360','accs'=>['153','154','155','156','157']],
    ['title'=>'CATALOUGE 2022','author'=>'N/A','publisher'=>'BPB PUBLICATIONS','edition'=>'N/A','purchase_date'=>'2022-06-15','price'=>'','accs'=>['158','159','160','161','162','163','164']],
    ['title'=>'CHECKLIST 2022','author'=>'N/A','publisher'=>'BPB PUBLICATIONS','edition'=>'N/A','purchase_date'=>'2022-06-15','price'=>'','accs'=>['165','166','167','168','169','170','171','172','173','174','175','176','177','178','179','180','181','182','183','184','185','186','187','188','189','190','191','192','193','194','195','196','197','198','199','200','201','202','203','204','205']],
    ['title'=>'SOFTWARE ENGINEERING A PRACTITIONER\'S APPROACH','author'=>'ROGER S. PRESSMAN','publisher'=>'MC Graw Hill Education','edition'=>'7TH','purchase_date'=>'2022-06-13','price'=>'680','accs'=>['206','207']],
    ['title'=>'SOFTWARE TESTING PRINCIPLES AND PRATICES','author'=>'NARESH CHAUHAN','publisher'=>'OXFORD','edition'=>'2ND','purchase_date'=>'2022-06-13','price'=>'520','accs'=>['208','209']],
    ['title'=>'FUNDAMENTALS OF SOFTWARE ENGINEERING','author'=>'RAJIB MALL','publisher'=>'PHI','edition'=>'4TH','purchase_date'=>'2022-06-13','price'=>'260','accs'=>['210','211']],
    ['title'=>'OPERATING SYSTEM CONCEPTS','author'=>'ABRAHAM SILBERSCHATZ ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'9TH','purchase_date'=>'2022-06-13','price'=>'520','accs'=>['212','213']],
    ['title'=>'COMPUTER FUNDAMENTALS AND PROGRAMMING IN C','author'=>'PRADIP DEY ET AL','publisher'=>'OXFORD','edition'=>'2ND','purchase_date'=>'2022-06-13','price'=>'412','accs'=>['214']],
    ['title'=>'COMPUTER FUNDAMENTALS AND PROGRAMMING IN C','author'=>'REEMA THAREJA','publisher'=>'OXFORD','edition'=>'3RD','purchase_date'=>'2022-06-13','price'=>'420','accs'=>['215']],
    ['title'=>'COMPUTER FUNDAMENTAL AND SOFT SKILLS','author'=>'A. KUMAR','publisher'=>'T BALAJI PUBLICATION','edition'=>'3RD','purchase_date'=>'2022-05-07','price'=>'84','accs'=>['216','217','218','219','220']],
    ['title'=>'PC HARDWARE & COMPONENTS AND PC ARCHITECTURE','author'=>'PANKAJ MEHTA ET AL','publisher'=>'MARG PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'550','accs'=>['221','222','223','224','225']],
    ['title'=>'ADVANCED NETWORKS NETWORKING PERIPHERAL AND OPERATING SYSTEM SOFTWAR4E & TOOLS','author'=>'PROF. B.A DAMAHE ET AL','publisher'=>'MARG PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'550','accs'=>['226','227','228','229','230']],
    ['title'=>'PERSONALITY DEVELOPMENT AND DEVICES & APPLICATIONS','author'=>'PANKAJ MEHTA ET AL','publisher'=>'MARG PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'550','accs'=>['231','232','233','234','235']],
    ['title'=>'CCC- COURSE ON COMPUTER CONCEPTS','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'360','accs'=>['236','237','238','239','240']],
    ['title'=>'CCC- COURSE ON COMPUTER CONCEPTS','author'=>'V. MISHRA ET AL','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'120','accs'=>['241','242','243','244','245']],
    ['title'=>'DTP- DESKTOP PUBLISHING','author'=>'V. MISHRA ET AL','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'120','accs'=>['246','247','248','249','250']],
    ['title'=>'FUNDAMENTAL & OFFICE AUTOMATION','author'=>'V. MISHRA ET AL','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'111','accs'=>['251','252','253','254','255']],
    ['title'=>'ARTIFICIAL INTELLIGENCE','author'=>'STUART J. RUSSELL','publisher'=>'PEARSON','edition'=>'3RD','purchase_date'=>'2022-06-13','price'=>'687','accs'=>['256']],
    ['title'=>'TALLY PRIME WITH GST','author'=>'V. MISHRA ET AL','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-05-07','price'=>'177','accs'=>['257','258','259','260','261']],
    ['title'=>'DATABASE MANAGEMENT SYSTEMS','author'=>'RAGHU RAMAKRISHNAN','publisher'=>'MC Graw Hill Education','edition'=>'3RD','purchase_date'=>'2022-06-13','price'=>'660','accs'=>['262']],
    ['title'=>'DATABASE SYSTEM CONCEPTS','author'=>'ABRAHAM SILBERSCHATZ ET AL','publisher'=>'MC Graw Hill Education','edition'=>'6TH','purchase_date'=>'2022-06-13','price'=>'700','accs'=>['263']],
    ['title'=>'FUNDAMENTALS DATABASE SYSTEMS','author'=>'RAMEZ ELMASRI ET AL','publisher'=>'PEARSON','edition'=>'7TH','purchase_date'=>'2022-06-13','price'=>'711','accs'=>['264']],
    ['title'=>'DIGITAL ELECTRONICS WITH ARDUINO','author'=>'BOB DUKISH','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'498','accs'=>['265','266','267','268','269']],
    ['title'=>'GETTING STARTED WITH RPA USING AUTOMATION ANYWHERE','author'=>'VAIBHAV SRIVASTAVA','publisher'=>'BPB PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'849','accs'=>['270','271']],
    ['title'=>'FUNDAMENTAL OF CYBER SECURITY','author'=>'MAYANK BHUSAN ET AL','publisher'=>'BPB PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'360','accs'=>['272','273']],
    ['title'=>'CLOUD COMPUTING','author'=>'KAMALKANT HIRAN ET AL','publisher'=>'BPB PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'899','accs'=>['274','275']],
    ['title'=>'CRYPTOGRAPHY AND NETWORK SECURITY','author'=>'BHUSAN TRIVEDI ET AL','publisher'=>'BPB PUBLICATION','edition'=>'1ST','purchase_date'=>'2022-06-15','price'=>'999','accs'=>['276','277']],
    ['title'=>'CRYPTOGRAPHY AND NETWORK SECURITY','author'=>'WILLIAN STALLINGS','publisher'=>'PEARSON','edition'=>'7TH','purchase_date'=>'2022-06-13','price'=>'543','accs'=>['278']],
    ['title'=>'CRYPTOGRAPHY AND NETWORK SECURITY','author'=>'BEHROUZ A FOROUZAN ET AL','publisher'=>'MC Graw Hill Education','edition'=>'3RD','purchase_date'=>'2022-08-04','price'=>'650','accs'=>['279']],
    ['title'=>'SECURITY IN COMPUTING','author'=>'`CHAR4LES P. PFLEEGER ET AL','publisher'=>'PEARSON','edition'=>'4TH','purchase_date'=>'2022-06-13','price'=>'719','accs'=>['280']],
    ['title'=>'FUNDAMENTALS OF DATABASE SYSTEMS','author'=>'RAMEZ ELMASRI ET AL','publisher'=>'PEARSON','edition'=>'7TH','purchase_date'=>'2022-06-13','price'=>'711','accs'=>['281']],
    ['title'=>'DISTRIBUTED DATABASES','author'=>'STEFANO CERI ET AL','publisher'=>'MC Graw Hill Education','edition'=>'INDIAN EDITION','purchase_date'=>'2022-08-04','price'=>'795','accs'=>['282']],
    ['title'=>'LET US "C"','author'=>'YASHAVANT KANETKAR','publisher'=>'BPB PUBLICATIONS','edition'=>'15TH','purchase_date'=>'2022-08-04','price'=>'297','accs'=>['283']],
    ['title'=>'INFORMATION THEORY, CODING AND CRYPTOGRAPHY','author'=>'RANJAN BOSE','publisher'=>'MC Graw Hill Education','edition'=>'3RD','purchase_date'=>'2022-08-04','price'=>'625','accs'=>['284']],
    ['title'=>'INTRODUCTION TO AI AND EXPERT SYSTEMS','author'=>'DAN W. PATTERSON','publisher'=>'PHI','edition'=>'EASTERN ECONOMY EDITION','purchase_date'=>'2022-08-04','price'=>'275','accs'=>['285']],
    ['title'=>'CRYPTOGRAPHY AND NETWORK SECURITY','author'=>'ATUL KAHATE','publisher'=>'MC Graw Hill Education','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'595','accs'=>['286']],
    ['title'=>'CRYPTOGRAPHY AND NETWORK SECURITY','author'=>'BEHROUZ A FOROUZAN ET AL','publisher'=>'MC Graw Hill Education','edition'=>'SPECIAL INDIAN EDITION','purchase_date'=>'2022-08-04','price'=>'650','accs'=>['287']],
    ['title'=>'AN INTRODUCTION TO IOT','author'=>'RAHUL DUBEY','publisher'=>'CENGAGE','edition'=>'INDIA EDITION','purchase_date'=>'2022-08-04','price'=>'550','accs'=>['288']],
    ['title'=>'THE COMPLETE REFERENCE J2EE','author'=>'JIM KEOGH','publisher'=>'MC Graw Hill Education','edition'=>'INDIAN EDITION','purchase_date'=>'2022-08-04','price'=>'765','accs'=>['289']],
    ['title'=>'THE COMPLETE REFERENCE HTML AND CSS','author'=>'THOMAS A. POWELL','publisher'=>'MC Graw Hill Education','edition'=>'5TH','purchase_date'=>'2022-08-04','price'=>'850','accs'=>['290']],
    ['title'=>'INTERNET AND WORLD WIDE WEB','author'=>'PAUL DEITEL ET AL','publisher'=>'PEARSON','edition'=>'5TH','purchase_date'=>'2022-08-04','price'=>'799','accs'=>['291']],
    ['title'=>'DATA STRUCTURES USING "C"','author'=>'AARON M. TENENBAUM ET AL','publisher'=>'PEARSON','edition'=>'12TH','purchase_date'=>'2022-08-04','price'=>'650','accs'=>['292']],
    ['title'=>'PYTHON PROGRAMMING USING PROBLEM SOLVING APPROACH','author'=>'REEMA THAREJA','publisher'=>'OXFORD','edition'=>'11TH','purchase_date'=>'2022-08-04','price'=>'550','accs'=>['293']],
    ['title'=>'ADVANCED CONCEPTS IN OPERATING SYSTEMS','author'=>'MUKESH SINGHAL ET AL','publisher'=>'MC Graw Hill Education','edition'=>'INDIAN EDITION','purchase_date'=>'2022-08-04','price'=>'625','accs'=>['294']],
    ['title'=>'CLOUD COMPUTING','author'=>'TASNEEM BANO REHMAN','publisher'=>'NEW AGE INTERNATIONAL PUBLISHERS','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'275','accs'=>['295']],
    ['title'=>'MOBILE COMPUTING','author'=>'ASOKE K TALUKDER RT AL','publisher'=>'MC Graw Hill Education','edition'=>'2ND','purchase_date'=>'2022-08-04','price'=>'765','accs'=>['296']],
    ['title'=>'Object Oriented Programming IN C++','author'=>'ROBERT LAFORE','publisher'=>'PEARSON','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'550','accs'=>['297']],
    ['title'=>'PRATIYOGITA DARPAN','author'=>'NA','publisher'=>'','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'125','accs'=>['298']],
    ['title'=>'SCIENCE REPORTER','author'=>'NA','publisher'=>'CSIR','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'30','accs'=>['299']],
    ['title'=>'COMPITION SUCCESS','author'=>'NA','publisher'=>'ESR','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'120','accs'=>['300']],
    ['title'=>'KURUKSHETRA','author'=>'NA','publisher'=>'MONIDEEPA MUKERJEE','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'22','accs'=>['301']],
    ['title'=>'INDIA TODAY','author'=>'NA','publisher'=>'INDIA TODAY','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'50','accs'=>['302']],
    ['title'=>'CURRENT AFFAIRS.COM','author'=>'NA','publisher'=>'PAYAL JAIN','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'25','accs'=>['303']],
    ['title'=>'CURRENT AFFAIRS','author'=>'NA','publisher'=>'ONELINER','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'100','accs'=>['304']],
    ['title'=>'CURRENT AFFAIRS YEARLY 2022','author'=>'NA','publisher'=>'SPEEDY','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'140','accs'=>['305']],
    ['title'=>'ENGINEERING SUCCESS REVIEW','author'=>'NA','publisher'=>'CSR','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'60','accs'=>['306']],
    ['title'=>'PRATIYOGITA DARPAN','author'=>'NA','publisher'=>'UPKAR PRAKASHAN','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'125','accs'=>['307']],
    ['title'=>'INDIA FIRST','author'=>'NA','publisher'=>'SAINATH PANDA','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'10','accs'=>['308']],
    ['title'=>'THE WEEK','author'=>'NA','publisher'=>'MONARAMA BUILDING','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'60','accs'=>['309']],
    ['title'=>'INDIA TODAY','author'=>'NA','publisher'=>'INDIA TODAY','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'75','accs'=>['310']],
    ['title'=>'FRONTLINE','author'=>'NA','publisher'=>'THE HINDU GROUP','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'125','accs'=>['311']],
    ['title'=>'YOJANA','author'=>'NA','publisher'=>'MINIDEEPA MUKERJEE','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'30','accs'=>['312']],
    ['title'=>'CHRONICLE','author'=>'NA','publisher'=>'CHRONICLE INDIA','edition'=>'NA','purchase_date'=>'2022-09-19','price'=>'125','accs'=>['313','314']],
    ['title'=>'INDUSTRIAL AUTOMATION AND ROBOTICS','author'=>'ER. A.K GUPTA ET AL','publisher'=>'UNIVERSITIES SCIENCE PRESS','edition'=>'3RD','purchase_date'=>'2022-08-04','price'=>'325','accs'=>['315']],
    ['title'=>'OBJECT ORIENTED PROGRAMMING WITH C++','author'=>'REEMA THAREJA','publisher'=>'OXFORD','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'625','accs'=>['316']],
    ['title'=>'BIGDATA AND ANALYTICS','author'=>'SEEMA ACHARYA ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'2ND','purchase_date'=>'2022-08-04','price'=>'599','accs'=>['317']],
    ['title'=>'BIGDATA SCIENCE ANALYTICS AND MACHINE LEARNING','author'=>'V.K JAIN','publisher'=>'KHANNA PUBLISHERS','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'369','accs'=>['318']],
    ['title'=>'BIG DATA(BLACK BOOK)','author'=>'BLACK BOOK','publisher'=>'Dreamtec Press','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'999','accs'=>['319']],
    ['title'=>'AN INTRODUCTION TO IOT','author'=>'RAHUL DUBEY','publisher'=>'CENGAGE','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'550','accs'=>['320']],
    ['title'=>'PROGRAMMING IN ANSI C','author'=>'E. BALAGURUSAMY','publisher'=>'MC Graw Hill Education','edition'=>'7TH','purchase_date'=>'2022-08-04','price'=>'799','accs'=>['321']],
    ['title'=>'OPERATING SYSTEM CONCEPTS','author'=>'ABRAHAM SILBERSCHATZ ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'9TH','purchase_date'=>'2022-08-04','price'=>'625','accs'=>['322']],
    ['title'=>'MATLAB PROGRAMMING FOR ENGINEERS','author'=>'STEPHEN J. CHAPMAN','publisher'=>'CENGAGE','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'795','accs'=>['323']],
    ['title'=>'ARTIFICIAL INTELLIGENCE','author'=>'ELEINE RICH ET AL','publisher'=>'MC Graw Hill Education','edition'=>'3RD','purchase_date'=>'2022-08-04','price'=>'275','accs'=>['324']],
    ['title'=>'COMPUTER NETWORKING','author'=>'JAMES F. KUROSE ET AL','publisher'=>'PEARSON','edition'=>'5TH','purchase_date'=>'2022-08-04','price'=>'765','accs'=>['325']],
    ['title'=>'FUNDAMENTALS OF DIGITAL CIRCUITS','author'=>'A. ANAND KUMAR','publisher'=>'PHI','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'600','accs'=>['326','327']],
    ['title'=>'PRINCIPLES OF MULTIMEDIA','author'=>'RANJAN PAREKH','publisher'=>'MC Graw Hill Education','edition'=>'2ND','purchase_date'=>'2022-08-04','price'=>'765','accs'=>['328','329']],
    ['title'=>'DESIGNING THE IOT','author'=>'ADRIAN MCEWEN ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'599','accs'=>['330']],
    ['title'=>'ACCOUNTING TEXT AND CASES','author'=>'ROBERT N. ANTHONY','publisher'=>'MC Graw Hill Education','edition'=>'INDIAN EDITION','purchase_date'=>'2022-08-04','price'=>'800','accs'=>['331']],
    ['title'=>'E-BUSI9NESS AND E-COMMERCE','author'=>'DAVE CHAFFEY','publisher'=>'PEARSON','edition'=>'5TH','purchase_date'=>'2022-08-04','price'=>'899','accs'=>['332']],
    ['title'=>'FINANCIAL ACCOUNTING','author'=>'R. NARAYANASWAMY','publisher'=>'PHI','edition'=>'6TH','purchase_date'=>'2022-08-04','price'=>'595','accs'=>['333']],
    ['title'=>'COMPUTER ALGORITHMS','author'=>'ELLIS HOROWITZ','publisher'=>'UNIVERSITIES PRESS','edition'=>'2ND','purchase_date'=>'2022-08-04','price'=>'595','accs'=>['334']],
    ['title'=>'DIGITAL DESIGN','author'=>'M. MORRIS MANO ET AL','publisher'=>'PEARSON','edition'=>'6TH','purchase_date'=>'2022-08-04','price'=>'699','accs'=>['335']],
    ['title'=>'CMOS DIGITAL INTEGRATED CIRCUITS','author'=>'SUNG-MO KANG','publisher'=>'MC Graw Hill Education','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'595','accs'=>['336']],
    ['title'=>'MICROPROCESSOR ARCHITECTURE, PROGRAMMING AND APPLICATIONS WITH THE 8085','author'=>'RAMESH GAONKAR','publisher'=>'PENRAM','edition'=>'6TH','purchase_date'=>'2022-08-04','price'=>'595','accs'=>['337']],
    ['title'=>'MICROELECTRONICS CIRCUITS','author'=>'ADLS SODRA ET AL','publisher'=>'OXFORD','edition'=>'6TH','purchase_date'=>'2022-08-04','price'=>'725','accs'=>['338']],
    ['title'=>'COMPUTER ARCHITECTURE','author'=>'DAVID A PATTERSON ET AL','publisher'=>'ELESVIER','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'595','accs'=>['339']],
    ['title'=>'COMPUTER ORGANIZATION AND DESIGN','author'=>'DAVID A PATTERSON ET AL','publisher'=>'ELESVIER','edition'=>'5TH','purchase_date'=>'2022-08-04','price'=>'665','accs'=>['340']],
    ['title'=>'FINANCIAL ACCOUNTING','author'=>'PAUL D. KIMMEL ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'7TH','purchase_date'=>'2022-08-04','price'=>'460','accs'=>['341']],
    ['title'=>'THE 8088 AND 8086 MICROPROCESSORS','author'=>'WALTER A. TRIEBEL ET AL','publisher'=>'PEARSON','edition'=>'4TH','purchase_date'=>'2022-08-04','price'=>'725','accs'=>['342']],
    ['title'=>'RENEWABLE ENERGY RESOURCES','author'=>'JOHN TWIDELL ET AL','publisher'=>'ROUTLEDGE','edition'=>'3RD','purchase_date'=>'2022-08-04','price'=>'695','accs'=>['343']],
    ['title'=>'ADVANCED CONCEPTS IN OPERATING SYSTEMS','author'=>'MUKESH SINGHAL ET AL','publisher'=>'MC Graw Hill Education','edition'=>'INDIAN EDITION','purchase_date'=>'2022-08-04','price'=>'625','accs'=>['344']],
    ['title'=>'INTERNATIONAL JOURNAL OF DIGITAL TECHNOLOGIES','author'=>'NIELIT TEAM','publisher'=>'NIELIT','edition'=>'1ST','purchase_date'=>'2023-02-21','price'=>'','accs'=>['345','346','347']],
    ['title'=>'MULTIMEDIA MAGIC','author'=>'S. GOKUL','publisher'=>'BPB PUBLICATIONS','edition'=>'2ND','purchase_date'=>'2023-02-27','price'=>'253','accs'=>['348','349','350','351','352','353','354','355']],
    ['title'=>'INTRODUCTION TO MULTIMEDIA','author'=>'PROF. SATISH JAIN ET AL','publisher'=>'BPB PUBLICATIONS','edition'=>'1ST','purchase_date'=>'2023-02-27','price'=>'324','accs'=>['356','357','358','359','360']],
    ['title'=>'PYTHON PROGRAMMING(ENGLISH VERSION)','author'=>'P.K PANDEY','publisher'=>'T BALAJI PUBLICATION','edition'=>'2ND','purchase_date'=>'2023-02-22','price'=>'177','accs'=>['361','362','363','364','365','366','367']],
    ['title'=>'IT TOOLS AND NETWORK BASICS IN SAMPLE ENGLISH','author'=>'P.K PANDEY','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2023-02-22','price'=>'177','accs'=>['368','369','370','371','372','373','374']],
    ['title'=>'IOT & ITS APPLICATIONS IN SAMPLE ENGLISH','author'=>'P.K PANDEY','publisher'=>'T BALAJI PUBLICATION','edition'=>'2ND','purchase_date'=>'2023-02-22','price'=>'177','accs'=>['375','376','377','378','379','380','381']],
    ['title'=>'WEB DESIGNING AND PUBLISHING IN SAMPLE ENGLISH','author'=>'P.K PANDEY','publisher'=>'T BALAJI PUBLICATION','edition'=>'1ST','purchase_date'=>'2023-02-22','price'=>'177','accs'=>['382','383','384','385','386','387','388']],
    ['title'=>'DESIGNING THE IOT','author'=>'ADRIAN MCEWEN ET AL','publisher'=>'WILEY INDIA PVT. TLD.','edition'=>'1ST','purchase_date'=>'2022-08-04','price'=>'599','accs'=>['389']],
    ['title'=>'27th Annual Report 2021-2022','author'=>'NIELIT HQ','publisher'=>'NIELIT','edition'=>'27TH','purchase_date'=>'2023-05-29','price'=>'','accs'=>['390']],
    ['title'=>'A Project Report On E-Calculator','author'=>'Kantikeswar Nayak(46832-2017','publisher'=>'Su Bharat Pati Mahavidyalaya, Samantiapalli(Ganjam)','edition'=>'1ST','purchase_date'=>'2023-06-01','price'=>'','accs'=>['391']],
    ['title'=>'A Project Report On "Smart Street Light for Energy Convertion"- IOT','author'=>'Bibhu Prasad Sethi','publisher'=>'NIELIT BBSR ("O" Level)','edition'=>'1ST','purchase_date'=>'2023-06-01','price'=>'','accs'=>['392']],
    ['title'=>'A Project Report On "Smart Street Light for Energy Convertion"- IOT','author'=>'Anup Kumar Murmu','publisher'=>'NIELIT BBSR ("O" Level)','edition'=>'1ST','purchase_date'=>'2023-06-01','price'=>'','accs'=>['393']],
    ['title'=>'A Project Report On "Smart Street Light for Energy Convertion"- IOT','author'=>'Biranchi Narayan Patnaik','publisher'=>'NIELIT BBSR ("O" Level)','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['394']],
    ['title'=>'Project Report on "A Complete Solution for travel Ideas"','author'=>'1. Mahesh k. Naik 2. Snehalata Dalai','publisher'=>'NIELIT BBSR ("O" Level)','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['395']],
    ['title'=>'Project Report on "A Complete Solution for travel Ideas"','author'=>'Ipsita Nayak','publisher'=>'NIELIT BBSR ("O" Level)','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['396']],
    ['title'=>'A Project Report On "Smart Street Light for Energy Convertion"- IOT','author'=>'Jitam K. Nayak','publisher'=>'NIELIT BBSR','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['397']],
    ['title'=>'Project Report on "Home Automation System" - IOT','author'=>'1. Balaji Pradhan 2. Rani Munda','publisher'=>'NIELIT BBSR','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['398']],
    ['title'=>'Project Report on "Home Automation System" - IOT','author'=>'Bisnupriya Gochayat','publisher'=>'NIELIT BBSR','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['399']],
    ['title'=>'Project Report on "Home Automation System" - IOT','author'=>'Priti Munda','publisher'=>'NIELIT BBSR','edition'=>'','purchase_date'=>'2023-06-01','price'=>'','accs'=>['400']],
    ['title'=>'Geossary of Adminstrative Terms (English-Hindi)','author'=>'Nanda Kishor Pandoya','publisher'=>'Govt. Of India','edition'=>'7th','purchase_date'=>'2023-06-23','price'=>'','accs'=>['401','402','403','404','405','406']],
    ['title'=>'A hand gesture control smart whel chair','author'=>'sambit binakar, biswajit sahu, debasish nayak','publisher'=>'NIELIT BBSR (project) intership','edition'=>'','purchase_date'=>'2023-06-26','price'=>'','accs'=>['407']],
    ['title'=>'smart health monitoring using GSM','author'=>'monalisha das, subhasmita dash','publisher'=>'NIELIT BBSR (project) intership','edition'=>'','purchase_date'=>'2023-06-26','price'=>'','accs'=>['408']],
    ['title'=>'smart garbage monitoring sytem','author'=>'truptirekha sahu, upasana mohapatra','publisher'=>'NIELIT BBSR (project) intership','edition'=>'','purchase_date'=>'2023-06-26','price'=>'','accs'=>['409']],
    ['title'=>'wealth reporting','author'=>'alisha moharathi , priyanka paul, ompriya dash','publisher'=>'NIELIT BBSR (project) intership','edition'=>'','purchase_date'=>'2023-06-26','price'=>'','accs'=>['410']],
    ['title'=>'smart car parking system','author'=>'harapriya singh     jyotsnamaharani sahoo','publisher'=>'NIELIT BBSR (project) O level','edition'=>'','purchase_date'=>'2023-06-27','price'=>'','accs'=>['411','412','413','414']],
    ['title'=>'multimedia power bank','author'=>'radharaman das      pritiranjan behera     purnasyati sahoo     abhishek acharya','publisher'=>'NIELIT BBSR (project) intership','edition'=>'','purchase_date'=>'2023-07-06','price'=>'','accs'=>['415']],
    ['title'=>'project report on travel ideas','author'=>'parameswar tkdu','publisher'=>'o level student  project report','edition'=>'','purchase_date'=>'2023-07-07','price'=>'','accs'=>['416']],
    ['title'=>'project report on travel ideas','author'=>'abhijit singh','publisher'=>'o level student  project report','edition'=>'','purchase_date'=>'2023-07-07','price'=>'','accs'=>['417']],
    ['title'=>'project report on travel ideas','author'=>'ansuman dora','publisher'=>'o level student  project report','edition'=>'','purchase_date'=>'2023-07-07','price'=>'','accs'=>['418']],
    ['title'=>'project report on travel ideas','author'=>'rameswar murmu','publisher'=>'o level student  project report','edition'=>'','purchase_date'=>'2023-07-07','price'=>'','accs'=>['419']],
    ['title'=>'project report on travel ideas','author'=>'k yashwant kumar','publisher'=>'o level student  project report','edition'=>'','purchase_date'=>'2023-07-24','price'=>'','accs'=>['420']],
    ['title'=>'IOT -Based Home Automation System','author'=>'ashutosh mohapatra       khajapati nayak                   swikruti mishra       subhadarshini baral','publisher'=>'IOT-Intership','edition'=>'','purchase_date'=>'2023-08-03','price'=>'','accs'=>['421']],
    ['title'=>'Computer Fundamental','author'=>'V.mishra','publisher'=>'TBP','edition'=>'1st','purchase_date'=>'2023-12-04','price'=>'350','accs'=>['422']],
    ['title'=>'Ethical hacking','author'=>'V.mishra','publisher'=>'TBP','edition'=>'1st','purchase_date'=>'2023-12-04','price'=>'200','accs'=>['423']],
    ['title'=>'Employability skills','author'=>'V.mishra','publisher'=>'TBP','edition'=>'1st','purchase_date'=>'2023-12-04','price'=>'250','accs'=>['424']],
    ['title'=>'Computer hardware','author'=>'V.mishra','publisher'=>'TBP','edition'=>'1st','purchase_date'=>'2023-12-04','price'=>'450','accs'=>['425']],
    ['title'=>'A project report on Virtual Assistant using Python and AI','author'=>'Suman Behera
Biswosanjeeb Jani
Debasish Pradhan
Jasbanta Ku. Hembram','publisher'=>'CHM O level','edition'=>'','purchase_date'=>'2023-12-11','price'=>'','accs'=>['426']],
    ['title'=>'A project report on An Analysis of the Bluetooth Technology','author'=>'Ranjan Kumar Senapati
Hemant Kumar Sahoo
Sridharanjan Samantaray
Prasenjit Roy Chowdhury
Santosh Kumar Pal','publisher'=>'CHMT O level','edition'=>'','purchase_date'=>'2023-12-12','price'=>'','accs'=>['427']],
    ['title'=>'A project report on Power Supply','author'=>'Sunil Kumar Barik
Sudhip Kumar Pradhan
Rajendra Jally
Jagadish Pradhan
Jharia Gumansingh','publisher'=>'CHMT O level','edition'=>'','purchase_date'=>'2023-12-12','price'=>'','accs'=>['428']],
    ['title'=>'A project report on SOHO Network Design for a Dental Clinic','author'=>'Sanjay Kumar Khandual
Chandan Kumar Das
Laxmi Dhar Biswal
Dilip Kumar Palai
Ashok Kumar Samal','publisher'=>'CHMT O level','edition'=>'','purchase_date'=>'2023-12-12','price'=>'','accs'=>['429']],
    ['title'=>'Project report on Bill Generator','author'=>'Chandan Behera
Dipun Kumar Behera','publisher'=>'O level','edition'=>'','purchase_date'=>'2023-12-19','price'=>'','accs'=>['430']],
    ['title'=>'Project report on Language Translator in Python using Google APIs','author'=>'Gayatri Tanaya Behera
Manisha Dash
Gayatree Singh
Manaswini Das','publisher'=>'O level','edition'=>'','purchase_date'=>'2024-01-05','price'=>'','accs'=>['431']],
    ['title'=>'Employability skills','author'=>'V.mishra','publisher'=>'TBP','edition'=>'1st','purchase_date'=>'2024-01-18','price'=>'250','accs'=>['432','433','434','435','436','437','438','439','440','441','442','443','444','445','446','447','448','449','450','451']],
    ['title'=>'A Project Report on Banking Account Management','author'=>'Bhaskar Rao Baddu              Suvendu Kumar Ray                  Muna Bisai','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['452']],
    ['title'=>'A Project Report on Submitted in Partial Fulfilment of the Requirements For the Award of the Degree, Quiz Game on Python','author'=>'Mayadhar Sahoo                       Saran Kumar Patra                    Ashok Kumar Pattnaik  Badrinarayan Mohapatra','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['453']],
    ['title'=>'Hotel Management','author'=>'Pradeep Kumar Samal             Pandit Bibekanada Tripathy   Bidyut Bhusan Behera','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['454']],
    ['title'=>'Online Library Management System','author'=>'Bhaskarao Gorle                    Palisetti Shiva Ram Krishna Abhishek Dixit','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['455']],
    ['title'=>'Banking Management System','author'=>'Uttam Kumar Mohanty      Sushanta Kumar Paikaray       Surata Champati','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['456']],
    ['title'=>'Health Management System Using HTML & CSS','author'=>'Madhusudan Mohapatra        Sanjay Kumar Rout                            A Rupesh Kumar','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['457']],
    ['title'=>'Health Management System','author'=>'Laxmikanta Jujharsingh             Alekh Prakash Bodra                  Janak Kumar Tarai','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['458']],
    ['title'=>'Billing Software For Restaurant in India','author'=>'Tanmaya Kumar Rout     Rajiblochan Behera              Muktikanta Mahakud','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['459']],
    ['title'=>'Scietific Calculator','author'=>'Atul Kumar Singh                      Neeraj Bajpai                              Manoj Kumar','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['460']],
    ['title'=>'Restaurant Management Billing System','author'=>'Susanta Behera                     Rabindra Kumar Pathak         Sushanta Bhanja                Jaiprakash Rajbhar','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['461']],
    ['title'=>'Grocery Retail Billing Management System','author'=>'Kamal Sinha                             Lekhraj Bajiya                            Subrat Kumar Pradhan','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['462']],
    ['title'=>'Railway Ticket Reservation','author'=>'Biranchi Narayan Rout           Mohan Charan Sahoo                Sisira Kumar Das','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['463']],
    ['title'=>'Banking Management System','author'=>'Santosh Kumar Chinhara            Jami Tejeswararoo                  Srikanta Patsani                   Kamalakant Behera','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['464']],
    ['title'=>'Health Management System','author'=>'D. Tuna Reddy                        Jagannath Rout                              Ranjan Mishra','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['465']],
    ['title'=>'Hotel Management System','author'=>'Anand Tiwari                          Durgesh Chandra Pandey           Mahendra Singh Parihar         Jitendra Kumar','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['466']],
    ['title'=>'Automate Word Document Using Python (- Doc)','author'=>'Badal Kumar Sahoo          Antrayami Biswal                   Satyajeet Mohanty','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['467']],
    ['title'=>'Gym Management System','author'=>'Biplab Keshari Das                    Smruti Ranjan Dhal                  Deepak Kumar Sahoo','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['468']],
    ['title'=>'Poultry Broiler Farming','author'=>'Sunil Kumar Barik
Sandeep Kumar Rai                    Vinay Kumar Agnihotri','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['469']],
    ['title'=>'Button Ripple Effect','author'=>'Kartik Chandra Malik                   Shrinibar Swain                          Mangi Lal Saran','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['470']],
    ['title'=>'Grocery Retail Billing Management System','author'=>'Susanta Kumar Sahu               Debasish Jena                        Pradipta Kumar Guru','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['471']],
    ['title'=>'Snake Game Using Python','author'=>'Bipin Behari Palai                 Prasanta Kumar Panda           Bibhuti Chhual Singh','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['472']],
    ['title'=>'Music Player Using HTML, CSS, Javascript','author'=>'Akhilesh Sharma                 Jaswinder Singh                         Mukesh Yadav','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2024-04-08','price'=>'','accs'=>['473']],
    ['title'=>'Hospital Management System','author'=>'Rihana Banu','publisher'=>'A\' level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['474']],
    ['title'=>'Networking in University','author'=>'Diptiranjan Moharana','publisher'=>'A\' level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['475']],
    ['title'=>'Intrusion Detection System','author'=>'Prakash Chandra Baral','publisher'=>'A\' level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['476']],
    ['title'=>'Wind - Solar Hybrid Energy Project and Production Analysis Report','author'=>'Jeyasenthil V                                 Susil Kumar Nahak                       Pratap Kumar Dash','publisher'=>'CHM-T (O) level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['477']],
    ['title'=>'Smart Home Low Cost','author'=>'Sudhir Kumar Barik                    Chandra Bhanu Nayak           Niranjan Mohapatra                 Bikash Chandra Biswal','publisher'=>'CHM-T (O) level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['478']],
    ['title'=>'Home Automation Using IOT','author'=>'NC Maiti                                            AK Giri                                                  B Adhikari                                          LK Saren                                             SK Roy','publisher'=>'CHM-T (O) level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['479']],
    ['title'=>'RFID - Based Driving License System To Start Engine','author'=>'Rabi Narayan Barik                       Kanhu Charan Nayak                   Matta Krishna                              Ashish Kumar Sethi                             Satrujeet JK Puhan','publisher'=>'CHM-T (O) level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['480']],
    ['title'=>'Smart Car With RFID Based Driving License System','author'=>'Rajeev R                                    Rakesh Kumar                               Ravi Narayan Muni                        Raj Kishor Nayak                                K Pavan Kumar','publisher'=>'CHM-T (O) level','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['481']],
    ['title'=>'Cyber Threats And its Security','author'=>'Biswajit Sahoo                          Ramesh Kumar Swain                 Bijay Kumar Pradhan','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['482']],
    ['title'=>'Result Sheet of NIELET Course Ser No. 205','author'=>'Binesh Kumar Sethi                    Girish Kumar Pathi                  Lokanath Gujiri','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['483']],
    ['title'=>'Mastering Libreoffice Impress','author'=>'Muralidas P                                Jadhav Ganesh Malhari        Pradeep V','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['484']],
    ['title'=>'Practical Test of Accounting of Course SER No - 205','author'=>'Rajendra Oraon                          Nitesh Kumar Nayak                   Sarada Prasad Mishra             Minaketan Behera','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['485']],
    ['title'=>'Screen Less Display','author'=>'Sanjaya Kumar Nayak                Samir Ranjan Sahoo                   Pravu Prasad Pradhan          Prashant Kumar Sahu','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['486']],
    ['title'=>'Libre Office Impress','author'=>'Atul Mourya                        Baikuntha Das','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['487']],
    ['title'=>'Mastering Libreoffice Calc','author'=>'Longjam Motikumar Meetei       Madan Mohan Sahoo           Sudheesh Lal MS                               HK Verma','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['488']],
    ['title'=>'Libre Office','author'=>'Saroj Singh                               Sanjeeb Kumar Khamari            Kadam Pranil','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['489']],
    ['title'=>'About Libre Office','author'=>'Rama Kanta Mohanta              Jaydeb Paria                                 Pradeep Kumar Sen','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['490']],
    ['title'=>'Assessment of Practical Test Course Ser No - 205','author'=>'Kailash Chandra Panda            Manoj Kumar Barik                 Sridhara Sahu','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['491']],
    ['title'=>'Result Sheet of SBI Bank Employee 2024/25','author'=>'Ramesh Chandra Mohapatra      Kanhu Charan Senapati                Anil Kumar Behera','publisher'=>'CDE&OA','edition'=>'','purchase_date'=>'2025-04-02','price'=>'','accs'=>['492']],
    ['title'=>'Password Management System','author'=>'Rakesh Kumar Behera','publisher'=>'\'A\' Level','edition'=>'','purchase_date'=>'2025-07-18','price'=>'','accs'=>['493']],
    ['title'=>'File System Of Operating System','author'=>'Ashok Ghatak                           Bhuban Mihir Dash              Srinibash Swain                        Sambit Dash                              Santosh Kumar Patra','publisher'=>'CHM-T (O) level','edition'=>'','purchase_date'=>'2025-07-18','price'=>'','accs'=>['494']],
    ['title'=>'Cyberspace And The Law','author'=>'Jagannath Nayak','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['495']],
    ['title'=>'Real-World Threats and Defenses','author'=>'Manas Kumar Panda             Sudhansu Sahu                          Prafulla Tripathy','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['496']],
    ['title'=>'Simulated  Phishing Campaing','author'=>'Dogiparthi Subrahmanyhan         Goutham Readdy Komandla       Gentena Srikanth','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['497']],
    ['title'=>'Cybercrime-Mobile & Wireless Devices','author'=>'Sudhansu Sekhar Mahapatra     Sibaram Swain                      Sudhakar Swain','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['498']],
    ['title'=>'Block-Chain Based Secured Access Management System For Healthcare Records','author'=>'Dargano Sanapal                      Korada Gopi                                    Siva Rajesh mavauri                Madhu Lakkoju','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['499']],
    ['title'=>'SQL Injection In Web Application','author'=>'Sateesh Kosuru                       Chandaluri Suresh Kumar            ARE Prabuji','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['500']],
    ['title'=>'Password Strength Checker','author'=>'U Sree Selvakannan             Ganapati Bandiyavar                     Raju Kumar','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['501']],
    ['title'=>'Establishment Of PKI To Prevent Cyber Security Threats In India','author'=>'Pretish Kumar Parija                     Bijay Kumar Jena','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['502']],
    ['title'=>'TCP/Ip Model  In Data Communication And Networking','author'=>'Ujjwal Paul                           PurendraNayak                         Jitrandra Kumar','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['503']],
    ['title'=>'Phishing','author'=>'Uttam Kumar Koli                   Manish Malik                              Arvind Kumar Yadav','publisher'=>'Cyber Security Assistant','edition'=>'','purchase_date'=>'2025-07-21','price'=>'','accs'=>['504']],
    ['title'=>'Library  Management System','author'=>'Sourav Sahu','publisher'=>'\'A\' Level','edition'=>'','purchase_date'=>'2025-07-22','price'=>'','accs'=>['505']],
    ['title'=>'Telephony Service Configuration','author'=>'Deepak Kumar Jena','publisher'=>'\'A\' Level','edition'=>'','purchase_date'=>'2025-07-22','price'=>'','accs'=>['506']],
    ['title'=>'Image Steganography System','author'=>'Dyatman Basu','publisher'=>'\'A\' Level','edition'=>'','purchase_date'=>'2025-07-22','price'=>'','accs'=>['507']],
    ['title'=>'Virtual Assistant Using Python And AI','author'=>'Utkalika Swain','publisher'=>'\'A\' Level','edition'=>'','purchase_date'=>'2025-07-22','price'=>'','accs'=>['508']],
    ['title'=>'Hotel Management System','author'=>'Prakash Chandra Naik','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['509']],
    ['title'=>'Bank Managenet System','author'=>'Tapasi Purty                               Renuka Purti','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['510']],
    ['title'=>'Disaster Detection System','author'=>'Biplab Kumar Bag                       Sagar Naiak                                    Sukant Malik                            Gopinath Malik','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['511']],
    ['title'=>'Hyper Text Markup Language(HTML)','author'=>'Dumuni Marndi                           Lakasan Pradhan                    Sarojani Mallik','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['512']],
    ['title'=>'Portfolio Website','author'=>'Ganga Tiria','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['513']],
    ['title'=>'Tic Tac Toe Game In Python','author'=>'Snigdha Sucharita','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['514']],
    ['title'=>'Age Calculator','author'=>'Kairi Purty                                   Bapuji Kumar Naya','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['515']],
    ['title'=>'Mini  Chatbox Extension','author'=>'Sandeep Mallick','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['516']],
    ['title'=>'Online Food Service','author'=>'Shyama Bankira                             Trilochan Hembram                      Krushna Chandra Naik','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['517']],
    ['title'=>'Rock, Paper & Seissors Game In Python','author'=>'Soubhagyaranjan Behera            Pitha Marandi                                  Mithun Behera','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['518']],
    ['title'=>'Simple and Basic  Calculator Using HTML, JAVA & CSS Programming','author'=>'Sunil Kumar Sethi                               Devashish Kumar                           Divyankar Soren','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['519']],
    ['title'=>'Simple and LightWeight  Calculator','author'=>'Sasmita Sandil                               Susmita Kandulna                              Ganesh Hansdah','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['520']],
    ['title'=>'Personal Portfolio Website Using HTML & CSS','author'=>'Suraj Mallick                                 Gangaram Majhi                            Anil Kumar Naik','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['521']],
    ['title'=>'Mini Search Engine','author'=>'Sumit Ekka                                      Japina Pradhan                            Saraswati Banara','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-07-24','price'=>'','accs'=>['522']],
    ['title'=>'To Do List Using HTML,CSS & JAVA Script','author'=>'Abhishek Kumar                                  Hemant Nayak                                     Pradeep Tnwar','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['523']],
    ['title'=>'Countdown Clock & Timer Using Python','author'=>'Vinod R Sidhanti                                    Bhavesh Pandey                                 Devendra Kumar Pal','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['524']],
    ['title'=>'Attendance Management System','author'=>'Sanjaya Biswal                                            Susant Behera                                           Bapindra Sahu','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['525']],
    ['title'=>'Hotel Management System','author'=>'Hemant Narayan Kumar Sahu          Himadri Bhushan Tripathy                Sudhir Patro','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['526']],
    ['title'=>'Library Website On HTML, CSS & JS','author'=>'Sisir Kumar Pal                                     Piriya Jagan Mohan Rao','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['527']],
    ['title'=>'To Do List Using HTML,CSS & JAVA Script','author'=>'Satyabrata Pattanaik                        Damdarudhar Rout','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['528']],
    ['title'=>'Library Management System','author'=>'Sachin Kadian                                    Gurugubelli K Chaitanya                   Rameshwar Bajiya','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['529']],
    ['title'=>'Hotel and Restaurant Management System','author'=>'Radhey Shyam Bhati                          Ramkishan Mahala                           Aakash Jaiswal','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['530']],
    ['title'=>'To Do List Using HTML,CSS & JAVA Script','author'=>'Nagendra Kumar Rana                          Jitendra Kumar Lenka                             Damodar Pradhan','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['531']],
    ['title'=>'Sudent Utilities System','author'=>'M Seshagiri Vasu                                Peddisetly Harikrishna                            Siva Prasad Tammana','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['532']],
    ['title'=>'To Do List Using HTML,CSS & JAVA Script','author'=>'Gopal Krushna Naik                                 Dillipa Kumar Das                                 Susant Kumar Ray','publisher'=>'IT \'O\' level','edition'=>'','purchase_date'=>'2025-08-11','price'=>'','accs'=>['533']],
    ['title'=>'Mobile Charging Power Section','author'=>'Manoj Kumar Yadav                              Subrat Kumar Pradhan','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['534']],
    ['title'=>'Network Signal Repair','author'=>'Niranjan Pandti                                          Bedaprakash Swain','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['535']],
    ['title'=>'Network Signal Repair','author'=>'Jyoti Ranjan Moharan                          Suresh Kumar Pati','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['536']],
    ['title'=>'Network Signal Repair','author'=>'Shaik  Jamal Ahamad                     Gulla Sathagiri','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['537']],
    ['title'=>'Network Signal Repair','author'=>'Simadreenath Sahoo','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['538']],
    ['title'=>'Network Signal Repair','author'=>'Sarada Prasad Tripathy','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['539']],
    ['title'=>'Mobile Service Centre','author'=>'Prasant Kumar Sahoo','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['540']],
    ['title'=>'Mobile Service Centre','author'=>'Manoranjan Sahoo','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['541']],
    ['title'=>'Mobile Service Centre','author'=>'Vineet Kumar Shukla','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['542']],
    ['title'=>'Mobile Service Centre','author'=>'S S Manoj Kumar','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['543']],
    ['title'=>'Mobile Service Centre','author'=>'Vinod Chopra','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['544']],
    ['title'=>'Mobile Service Centre','author'=>'Santosh Kumar Pradhan','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['545']],
    ['title'=>'Network Signal Repair','author'=>'Srinibas Nayak','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['546']],
    ['title'=>'Chaeging And Power Satation','author'=>'Manirupas Aremanda','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['547']],
    ['title'=>'Network Signal Repair','author'=>'Sitaram Patra','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['548']],
    ['title'=>'Damaged Display Replacement','author'=>'Rajani Kant Jena','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['549']],
    ['title'=>'Damaged Display Replacement','author'=>'Asis Kumar Bhoi','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['550']],
    ['title'=>'Damaged Display Replacement','author'=>'Keailsh Kumar Baghel','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['551']],
    ['title'=>'Damaged Display Replacement','author'=>'Jagannath Behera','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['552']],
    ['title'=>'Damaged Display Replacement','author'=>'Premchand PM','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['553']],
    ['title'=>'Broken Display Replacement','author'=>'Rajnesh Kumar','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['554']],
    ['title'=>'Mobile Service Centre Simulation','author'=>'Pradipta Kumar Rout','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['555']],
    ['title'=>'Mobile Service Centre Simulation','author'=>'Shyam Bhiari Godhar','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['556']],
    ['title'=>'Mobile Service Centre Simulation','author'=>'Ashok Kumar Sahu','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['557']],
    ['title'=>'Mobile Service Centre Simulation','author'=>'V Swarajya Kumar Patra','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['558']],
    ['title'=>'Network Signal Repair','author'=>'Pratap Kumar Sahoo','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['559']],
    ['title'=>'Mobile Service Centre Simulation','author'=>'Asish Rout','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['560']],
    ['title'=>'Mobile Service Centre Simulation','author'=>'Patita Paban Mohanty','publisher'=>'RAMA SP','edition'=>'','purchase_date'=>'2026-03-04','price'=>'','accs'=>['561']],
    ];
}

function doInstall(mysqli $conn, int $centreId): bool
{
    ensureLibraryTables($conn);

    $stmt = $conn->prepare(
        'INSERT INTO library_books
            (accession_no, title, author, publisher, edition, purchase_date, price, source, status, created_by, centre_id)
         VALUES (?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, 0), \'Purchased\', \'available\', \'' . IMPORT_TAG . '\', ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title), author = VALUES(author), publisher = VALUES(publisher),
            edition = VALUES(edition), purchase_date = VALUES(purchase_date), price = VALUES(price)'
    );
    if (!$stmt) { out('Prepare failed: ' . $conn->error); return false; }

    $inserted = 0; $updated = 0; $failed = 0;
    $conn->begin_transaction();
    foreach (books() as $b) {
        $priceVal = ($b['price'] === '') ? 0.0 : (float) $b['price'];
        foreach ($b['accs'] as $acc) {
            $acc = strtoupper(trim($acc));
            $stmt->bind_param(
                'sssssdsi',
                $acc, $b['title'], $b['author'], $b['publisher'], $b['edition'],
                $b['purchase_date'], $priceVal, $centreId
            );
            if ($stmt->execute()) {
                if ($stmt->affected_rows === 1) { $inserted++; }
                elseif ($stmt->affected_rows === 2) { $updated++; }
            } else {
                $failed++; out('  Failed acc ' . $acc . ': ' . $stmt->error);
            }
        }
    }
    $conn->commit();
    $stmt->close();
    out("Done. Inserted: $inserted, Updated: $updated, Failed: $failed (centre #$centreId).");
    return $failed === 0;
}

function doVerify(mysqli $conn, int $centreId): void
{
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM library_books WHERE created_by = ? AND centre_id = ?");
    $tag = IMPORT_TAG;
    $stmt->bind_param('si', $tag, $centreId);
    $stmt->execute();
    $c = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    out("Imported rows for centre #$centreId: $c (expected 561).");
}

function doRollback(mysqli $conn, int $centreId): void
{
    $stmt = $conn->prepare("DELETE FROM library_books WHERE created_by = ? AND centre_id = ?");
    $tag = IMPORT_TAG;
    $stmt->bind_param('si', $tag, $centreId);
    $stmt->execute();
    out("Rolled back " . $stmt->affected_rows . " rows for centre #$centreId.");
    $stmt->close();
}

$action = 'install';
$argCentre = 0;
if (php_sapi_name() === 'cli') {
    $action = $argv[1] ?? 'install';
    $argCentre = (int) ($argv[2] ?? 0);
} else {
    // When launched from the admin Migrations panel the command arrives via a
    // global set by the runner (there are no query-string args in that flow).
    $action = $GLOBALS['migration_web_command'] ?? ($_GET['action'] ?? 'install');
    $argCentre = (int) ($_GET['centre_id'] ?? 0);
}

$centreId = resolveCentreId($conn, $argCentre > 0 ? $argCentre : $FORCE_CENTRE_ID);
// IMPORTANT: never call exit/die here. The admin runner include()s this file and
// emits a JSON envelope on shutdown; exiting mid-way corrupts that JSON response.
// Returning stops the script cleanly and lets the runner report our output.
if ($centreId <= 0) {
    return;
}

switch ($action) {
    case 'verify':   doVerify($conn, $centreId); break;
    case 'rollback': doRollback($conn, $centreId); break;
    case 'install':
    default:         doInstall($conn, $centreId); break;
}
