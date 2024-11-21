<?php

/**
 * @file tests/jobs/doi/DepositSubmissionTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for submission deposit job.
 */

namespace PKP\tests\jobs\doi;

use Mockery;
use APP\core\Application;
use PKP\tests\PKPTestCase;
use PKP\jobs\doi\DepositSubmission;
use APP\doi\Repository as DoiRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use APP\submission\Repository as SubmissionRepository;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PKP\context\Context;

#[RunTestsInSeparateProcesses]
#[CoversClass(DepositSubmission::class)]
class DepositSubmissionTest extends PKPTestCase
{
    /**
     * Get the serialized data based on application
     */
    protected function getSerializedJobData(): string
    {
        return match(Application::get()->getName()) {
            // serializion from OJS 3.4.0
            'ojs2' => <<<END
            O:30:"PKP\\jobs\\doi\\DepositSubmission":5:{s:15:"\0*\0submissionId";i:1;s:10:"\0*\0context";O:19:"APP\\journal\\Journal":6:{s:5:"_data";a:73:{s:2:"id";i:1;s:7:"urlPath";s:15:"publicknowledge";s:7:"enabled";b:1;s:3:"seq";i:1;s:13:"primaryLocale";s:2:"en";s:14:"currentIssueId";i:1;s:19:"automaticDoiDeposit";b:0;s:12:"contactEmail";s:20:"rvaca@mailinator.com";s:11:"contactName";s:11:"Ramiro Vaca";s:18:"copyrightYearBasis";s:5:"issue";s:31:"copySubmissionAckPrimaryContact";b:1;s:7:"country";s:2:"IS";s:8:"currency";s:3:"CAD";s:17:"defaultReviewMode";i:2;s:18:"disableSubmissions";b:0;s:15:"doiCreationTime";s:20:"copyEditCreationTime";s:9:"doiPrefix";s:7:"10.1234";s:13:"doiSuffixType";s:7:"default";s:13:"doiVersioning";b:0;s:19:"editorialStatsEmail";b:1;s:14:"emailSignature";s:141:"<br><br>—<br><p>This is an automated message from <a href="http://localhost/index.php/publicknowledge">Journal of Public Knowledge</a>.</p>";s:19:"enableAnnouncements";b:1;s:15:"enabledDoiTypes";a:2:{i:0;s:11:"publication";i:1;s:5:"issue";}s:10:"enableDois";b:1;s:19:"enableGeoUsageStats";s:8:"disabled";s:27:"enableInstitutionUsageStats";b:0;s:9:"enableOai";b:1;s:16:"isSushiApiPublic";b:1;s:12:"itemsPerPage";i:25;s:8:"keywords";s:7:"request";s:14:"mailingAddress";s:49:"123 456th Street Burnaby, British Columbia Canada";s:13:"membershipFee";d:0;s:16:"notifyAllAuthors";b:1;s:12:"numPageLinks";i:10;s:19:"numWeeksPerResponse";i:4;s:17:"numWeeksPerReview";i:4;s:10:"onlineIssn";s:9:"0378-5955";s:17:"paymentPluginName";s:13:"PaypalPayment";s:15:"paymentsEnabled";b:1;s:9:"printIssn";s:9:"0378-5955";s:14:"publicationFee";d:0;s:20:"publisherInstitution";s:24:"Public Knowledge Project";s:18:"purchaseArticleFee";d:0;s:18:"registrationAgency";s:14:"dataciteplugin";s:25:"submissionAcknowledgement";s:10:"allAuthors";s:20:"submitWithCategories";b:0;s:20:"supportedFormLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:16:"supportedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:26:"supportedSubmissionLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:12:"supportEmail";s:20:"rvaca@mailinator.com";s:11:"supportName";s:11:"Ramiro Vaca";s:15:"themePluginPath";s:7:"default";s:12:"abbreviation";a:1:{s:2:"en";s:25:"publicknowledgeJ Pub Know";}s:7:"acronym";a:1:{s:2:"en";s:6:"JPKJPK";}s:16:"authorGuidelines";a:2:{s:2:"en";s:1209:"<p>Authors are invited to make a submission to this journal. All submissions will be assessed by an editor to determine whether they meet the aims and scope of this journal. Those considered to be a good fit will be sent for peer review before determining whether they will be accepted or rejected.</p><p>Before making a submission, authors are responsible for obtaining permission to publish any material included with the submission, such as photos, documents and datasets. All authors identified on the submission must consent to be identified as an author. Where appropriate, research should be approved by an appropriate ethics committee in accordance with the legal requirements of the study's country.</p><p>An editor may desk reject a submission if it does not meet minimum standards of quality. Before submitting, please ensure that the study design and research argument are structured and articulated properly. The title should be concise and the abstract should be able to stand on its own. This will increase the likelihood of reviewers agreeing to review the paper. When you're satisfied that your submission meets this standard, please follow the checklist below to prepare your submission.</p>";s:5:"fr_CA";s:44:"##default.contextSettings.authorGuidelines##";}s:17:"authorInformation";a:2:{s:2:"en";s:586:"Interested in submitting to this journal? We recommend that you review the <a href="http://localhost/index.php/publicknowledge/about">About the Journal</a> page for the journal's section policies, as well as the <a href="http://localhost/index.php/publicknowledge/about/submissions#authorGuidelines">Author Guidelines</a>. Authors need to <a href="http://localhost/index.php/publicknowledge/user/register">register</a> with the journal prior to submitting or, if already registered, can simply <a href="http://localhost/index.php/index/login">log in</a> and begin the five-step process.";s:5:"fr_CA";s:715:"Intéressé-e à soumettre à cette revue ? Nous vous recommandons de consulter les politiques de rubrique de la revue à la page <a href="http://localhost/index.php/publicknowledge/about">À propos de la revue</a> ainsi que les <a href="http://localhost/index.php/publicknowledge/about/submissions#authorGuidelines">Directives aux auteurs</a>. Les auteurs-es doivent <a href="http://localhost/index.php/publicknowledge/user/register">s'inscrire</a> auprès de la revue avant de présenter une soumission, ou s'ils et elles sont déjà inscrits-es, simplement <a href="http://localhost/index.php/publicknowledge/login">ouvrir une session</a> et accéder au tableau de bord pour commencer les 5 étapes du processus.";}s:19:"beginSubmissionHelp";a:2:{s:2:"en";s:611:"<p>Thank you for submitting to the Journal of Public Knowledge. You will be asked to upload files, identify co-authors, and provide information such as the title and abstract.<p><p>Please read our <a href="http://localhost/index.php/publicknowledge/about/submissions" target="_blank">Submission Guidelines</a> if you have not done so already. When filling out the forms, provide as many details as possible in order to help our editors evaluate your work.</p><p>Once you begin, you can save your submission and come back to it later. You will be able to review and correct any information before you submit.</p>";s:5:"fr_CA";s:42:"##default.submission.step.beforeYouBegin##";}s:14:"clockssLicense";a:2:{s:2:"en";s:271:"This journal utilizes the CLOCKSS system to create a distributed archiving system among participating libraries and permits those libraries to create permanent archives of the journal for purposes of preservation and restoration. <a href="https://clockss.org">More...</a>";s:5:"fr_CA";s:315:"Cette revue utilise le système CLOCKSS pour créer un système d'archivage distribué parmi les bibliothèques participantes et permet à ces bibliothèques de créer des archives permanentes de la revue à des fins de conservation et de reconstitution. <a href="https://clockss.org">En apprendre davantage... </a>";}s:16:"contributorsHelp";a:2:{s:2:"en";s:504:"<p>Add details for all of the contributors to this submission. Contributors added here will be sent an email confirmation of the submission, as well as a copy of all editorial decisions recorded against this submission.</p><p>If a contributor can not be contacted by email, because they must remain anonymous or do not have an email account, please do not enter a fake email address. You can add information about this contributor in a message to the editor at a later step in the submission process.</p>";s:5:"fr_CA";s:40:"##default.submission.step.contributors##";}s:13:"customHeaders";a:1:{s:2:"en";s:41:"<meta name="pkp" content="Test metatag.">";}s:11:"description";a:2:{s:2:"en";s:123:"<p>The Journal of Public Knowledge is a peer-reviewed quarterly publication on the subject of public access to science.</p>";s:5:"fr_CA";s:146:"<p>Le Journal de Public Knowledge est une publication trimestrielle évaluée par les pairs sur le thème de l'accès du public à la science.</p>";}s:11:"detailsHelp";a:2:{s:2:"en";s:92:"<p>Please provide the following details to help us manage your submission in our system.</p>";s:5:"fr_CA";s:35:"##default.submission.step.details##";}s:17:"forTheEditorsHelp";a:2:{s:2:"en";s:278:"<p>Please provide the following details in order to help our editorial team manage your submission.</p><p>When entering metadata, provide entries that you think would be most helpful to the person managing your submission. This information can be changed before publication.</p>";s:5:"fr_CA";s:41:"##default.submission.step.forTheEditors##";}s:20:"librarianInformation";a:2:{s:2:"en";s:361:"We encourage research librarians to list this journal among their library's electronic journal holdings. As well, it may be worth noting that this journal's open source publishing system is suitable for libraries to host for their faculty members to use with journals they are involved in editing (see <a href="https://pkp.sfu.ca/ojs">Open Journal Systems</a>).";s:5:"fr_CA";s:434:"Nous incitons les bibliothécaires à lister cette revue dans leur fonds de revues numériques. Aussi, il peut être pertinent de mentionner que ce système de publication en libre accès est conçu pour être hébergé par les bibliothèques de recherche pour que les membres de leurs facultés l'utilisent avec les revues dans lesquelles elles ou ils sont impliqués (voir <a href="https://pkp.sfu.ca/ojs">Open Journal Systems</a>).";}s:13:"lockssLicense";a:2:{s:2:"en";s:273:"This journal utilizes the LOCKSS system to create a distributed archiving system among participating libraries and permits those libraries to create permanent archives of the journal for purposes of preservation and restoration. <a href="https://www.lockss.org">More...</a>";s:5:"fr_CA";s:314:"Cette revue utilise le système LOCKSS pour créer un système de distribution des archives parmi les bibliothèques participantes et afin de permettre à ces bibliothèques de créer des archives permanentes pour fins de préservation et de restauration. <a href="https://lockss.org">En apprendre davantage...</a>";}s:4:"name";a:2:{s:2:"en";s:27:"Journal of Public Knowledge";s:5:"fr_CA";s:36:"Journal de la connaissance du public";}s:16:"openAccessPolicy";a:2:{s:2:"en";s:176:"This journal provides immediate open access to its content on the principle that making research freely available to the public supports a greater global exchange of knowledge.";s:5:"fr_CA";s:217:"Cette revue fournit le libre accès immédiat à son contenu se basant sur le principe que rendre la recherche disponible au public gratuitement facilite un plus grand échange du savoir, à l'échelle de la planète.";}s:16:"privacyStatement";a:2:{s:2:"en";s:206:"<p>The names and email addresses entered in this journal site will be used exclusively for the stated purposes of this journal and will not be made available for any other purpose or to any other party.</p>";s:5:"fr_CA";s:193:"<p>Les noms et courriels saisis dans le site de cette revue seront utilisés exclusivement aux fins indiquées par cette revue et ne serviront à aucune autre fin, ni à toute autre partie.</p>";}s:17:"readerInformation";a:2:{s:2:"en";s:654:"We encourage readers to sign up for the publishing notification service for this journal. Use the <a href="http://localhost/index.php/publicknowledge/user/register">Register</a> link at the top of the home page for the journal. This registration will result in the reader receiving the Table of Contents by email for each new issue of the journal. This list also allows the journal to claim a certain level of support or readership. See the journal's <a href="http://localhost/index.php/publicknowledge/about/submissions#privacyStatement">Privacy Statement</a>, which assures readers that their name and email address will not be used for other purposes.";s:5:"fr_CA";s:716:"Nous invitons les lecteurs-trices à s'inscrire pour recevoir les avis de publication de cette revue. Utiliser le lien <a href="http://localhost/index.php/publicknowledge/user/register">S'inscrire</a> en haut de la page d'accueil de la revue. Cette inscription permettra au,à la lecteur-trice de recevoir par courriel le sommaire de chaque nouveau numéro de la revue. Cette liste permet aussi à la revue de revendiquer un certain niveau de soutien ou de lectorat. Voir la <a href="http://localhost/index.php/publicknowledge/about/submissions#privacyStatement">Déclaration de confidentialité</a> de la revue qui certifie aux lecteurs-trices que leur nom et leur courriel ne seront pas utilisés à d'autres fins.";}s:10:"reviewHelp";a:2:{s:2:"en";s:368:"<p>Review the information you have entered before you complete your submission. You can change any of the details displayed here by clicking the edit button at the top of each section.</p><p>Once you complete your submission, a member of our editorial team will be assigned to review it. Please ensure the details you have entered here are as accurate as possible.</p>";s:5:"fr_CA";s:34:"##default.submission.step.review##";}s:17:"searchDescription";a:1:{s:2:"en";s:116:"The Journal of Public Knowledge is a peer-reviewed quarterly publication on the subject of public access to science.";}s:19:"submissionChecklist";a:2:{s:2:"en";s:591:"<p>All submissions must meet the following requirements.</p><ul><li>This submission meets the requirements outlined in the <a href="http://localhost/index.php/publicknowledge/about/submissions">Author Guidelines</a>.</li><li>This submission has not been previously published, nor is it before another journal for consideration.</li><li>All references have been checked for accuracy and completeness.</li><li>All tables and figures have been numbered and labeled.</li><li>Permission has been obtained to publish all photos, datasets and other material provided with this submission.</li></ul>";s:5:"fr_CA";s:37:"##default.contextSettings.checklist##";}s:15:"uploadFilesHelp";a:2:{s:2:"en";s:249:"<p>Provide any files our editorial team may need to evaluate your submission. In addition to the main work, you may wish to submit data sets, conflict of interest statements, or other supplementary files if these will be helpful for our editors.</p>";s:5:"fr_CA";s:39:"##default.submission.step.uploadFiles##";}}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:9:"\0*\0agency";O:43:"APP\\plugins\\generic\\datacite\\DatacitePlugin":4:{s:10:"pluginPath";s:24:"plugins/generic/datacite";s:14:"pluginCategory";s:7:"generic";s:7:"request";N;s:58:"\0APP\\plugins\\generic\\datacite\\DatacitePlugin\0_exportPlugin";N;}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
            END,
            
            // serializion from OPS 3.4.0
            'ops' => <<<END
            O:30:"PKP\jobs\doi\DepositSubmission":5:{s:15:"\0*\0submissionId";i:19;s:10:"\0*\0context";O:17:"APP\server\Server":6:{s:5:"_data";a:61:{s:2:"id";i:1;s:7:"urlPath";s:15:"publicknowledge";s:7:"enabled";b:1;s:3:"seq";i:1;s:13:"primaryLocale";s:2:"en";s:7:"acronym";a:1:{s:2:"en";s:6:"JPKPKP";}s:16:"authorGuidelines";a:2:{s:2:"en";s:797:"<p>Researchers are invited to submit a preprint to be posted on this server. All preprints will be moderated to determine whether they meet the aims and scope of this server. Those considered to be a good fit will be posted and the author will be notified.</p><p>Before submitting a preprint, authors are responsible for obtaining permission to share any material included with the preprint, such as photos, documents and datasets. All authors identified on the preprint must consent to be identified as an author. Where appropriate, research should be approved by an appropriate ethics committee in accordance with the legal requirements of the study's country.</p><p> When you're satisfied that your preprint meets this standard, please follow the checklist below to prepare your submission.</p>";s:5:"fr_CA";s:44:"##default.contextSettings.authorGuidelines##";}s:17:"authorInformation";a:2:{s:2:"en";s:528:"Interested in submitting to this server? We recommend that you review the <a href="http://localhost/index.php/publicknowledge/about">About</a> page for the policies, as well as the <a href="http://localhost/index.php/publicknowledge/about/submissions#authorGuidelines">Author Guidelines</a>. Authors need to <a href="http://localhost/index.php/publicknowledge/user/register">register</a> prior to submitting or, if already registered, can simply <a href="http://localhost/index.php/index/login">log in</a> and begin the process.";s:5:"fr_CA";s:38:"##default.contextSettings.forAuthors##";}s:19:"beginSubmissionHelp";a:2:{s:2:"en";s:619:"<p>Thank you for posting your preprint at Public Knowledge Preprint Server. You will be asked to upload files, identify co-authors, and provide information such as the title and abstract.<p><p>Please read our <a href="http://localhost/index.php/publicknowledge/about/submissions" target="_blank">Submission Guidelines</a> if you have not done so already. When filling out the forms, provide as many details as possible in order to help our readers find your work.</p><p>Once you begin, you can save your submission and come back to it later. You will be able to review and correct any information before you submit.</p>";s:5:"fr_CA";s:767:"<p>Merci de votre soumission à la revue Public Knowledge Preprint Server. Il vous sera demandé de téléverser des fichiers, identifier des co-auteur.trice.s et fournir des informations comme le titre et le résumé.<p><p>Si vous ne l'avez pas encore fait, merci de consulter nos <a href="http://localhost/index.php/publicknowledge/about/submissions" target="_blank">Recommandations pour la soumission</a>. Lorsque vous remplissez les formulaires, merci de fournir autant de détails que possible pour aider nos éditeur.trice.s à évaluer votre travail. </p><p>Une fois que vous avez commencé, vous pouvez enregistrer votre soumission et y revenir plus tard. Vous pourrez alors réviser et modifier toutes les informations voulues avant de soumettre le tout.</p>";}s:12:"contactEmail";s:20:"rvaca@mailinator.com";s:11:"contactName";s:11:"Ramiro Vaca";s:16:"contributorsHelp";a:2:{s:2:"en";s:430:"<p>Add details for all of the contributors to this submission. Contributors added here will be sent an email confirmation of the submission.</p><p> If a contributor can not be contacted by email, because they must remain anonymous or do not have an email account, please do not enter a fake email address. You can add information about this contributor in a message to the moderators at a later step in the submission process.</p>";s:5:"fr_CA";s:613:"<p>Ajouter des informations relatives à tous les contributeurs.trices à cette soumission. Les contributeurs.trices ajouté.e.s ici se verront envoyer un courriel de confirmation de la soumission ainsi qu'une copie de toutes les décisions éditoriales enregistrées pour cette soumission.</p><p>Si un.e contributeur.trice ne peut être contacté.e par courriel parce qu'il ou elle doit demeurer anonyme ou n'a pas de compte de messagerie, veuillez ne pas entrer de courriel fictif. Vous pouvez ajouter des informations sur ce ou cette contributeur.trice à une étape ultérieure du processus de soumission.</p>";}s:7:"country";s:2:"IS";s:17:"defaultReviewMode";i:2;s:11:"description";a:2:{s:2:"en";s:109:"<p>The Public Knowledge Preprint Server is a preprint service on the subject of public access to science.</p>";s:5:"fr_CA";s:170:"<p>Le Serveur de prépublication de la connaissance du public est une service trimestrielle évaluée par les pairs sur le thème de l'accès du public à la science.</p>";}s:11:"detailsHelp";a:2:{s:2:"en";s:92:"<p>Please provide the following details to help us manage your submission in our system.</p>";s:5:"fr_CA";s:117:"<p>Veuillez fournir les informations suivantes afin de nous aider à gérer votre soumission dans notre système.</p>";}s:31:"copySubmissionAckPrimaryContact";b:0;s:24:"copySubmissionAckAddress";s:0:"";s:14:"emailSignature";s:146:"<br><br>—<br><p>This is an automated message from <a href="http://localhost/index.php/publicknowledge">Public Knowledge Preprint Server</a>.</p>";s:10:"enableDois";b:1;s:13:"doiSuffixType";s:7:"default";s:18:"registrationAgency";s:14:"crossrefplugin";s:18:"disableSubmissions";b:0;s:19:"editorialStatsEmail";b:1;s:17:"forTheEditorsHelp";a:2:{s:2:"en";s:236:"<p>Please provide the following details in order to help readers discover your preprint.</p><p>When entering metadata such as keywords, provide entries that you think would be most helpful to readers looking for research like yours.</p>";s:5:"fr_CA";s:329:"<p>S'il vous plaît, fournissez les détails suivants afin d'aider l'équipe éditoriale à gérer votre soumission.</p><p>Dans vos métadonnées, assurez vous de fournir des informations que vous pensez pouvoir être utile à la personne qui gérera votre soumission. Cette information peut être changée avant publication.</p>";}s:12:"itemsPerPage";i:25;s:8:"keywords";s:7:"request";s:20:"librarianInformation";a:2:{s:2:"en";s:286:"We encourage research librarians to list this server among their library's holdings. As well, it may be worth noting that this server's open source system is suitable for libraries to host for their faculty members to use (see <a href="https://pkp.sfu.ca">Public Knowledge Project</a>).";s:5:"fr_CA";s:41:"##default.contextSettings.forLibrarians##";}s:4:"name";a:2:{s:2:"en";s:32:"Public Knowledge Preprint Server";s:5:"fr_CA";s:55:"Serveur de prépublication de la connaissance du public";}s:16:"notifyAllAuthors";b:1;s:12:"numPageLinks";i:10;s:19:"numWeeksPerResponse";i:4;s:17:"numWeeksPerReview";i:4;s:16:"openAccessPolicy";a:2:{s:2:"en";s:175:"This server provides immediate open access to its content on the principle that making research freely available to the public supports a greater global exchange of knowledge.";s:5:"fr_CA";s:44:"##default.contextSettings.openAccessPolicy##";}s:16:"privacyStatement";a:2:{s:2:"en";s:204:"<p>The names and email addresses entered in this server site will be used exclusively for the stated purposes of this server and will not be made available for any other purpose or to any other party.</p>";s:5:"fr_CA";s:44:"##default.contextSettings.privacyStatement##";}s:17:"readerInformation";a:2:{s:2:"en";s:503:"We encourage readers to sign up for the posting notification service for this server. Use the <a href="http://localhost/index.php/publicknowledge/user/register">Register</a> link at the top of the home page. This list also allows the server to claim a certain level of support or readership. See the <a href="http://localhost/index.php/publicknowledge/about/submissions#privacyStatement">Privacy Statement</a>, which assures readers that their name and email address will not be used for other purposes.";s:5:"fr_CA";s:38:"##default.contextSettings.forReaders##";}s:10:"reviewHelp";a:2:{s:2:"en";s:188:"<p>Review the information you have entered before you complete your submission. You can change any of the details displayed here by clicking the edit button at the top of each section.</p>";s:5:"fr_CA";s:402:"<p>Révisez l'information que vous avez fourni avant de finaliser votre soumission. Vous pouvez modifier chaque détails affichés en cliquant sur le bouton d'édition en haut de chaque section.</p><p>Une fois votre soumission transmise, un membre de l'équipe éditoriale lui sera assigné afin de l'évaluer. S'il vous plaît, assurez vous que les détails fournis sont le plus exactes possibles.</p>";}s:25:"submissionAcknowledgement";s:10:"allAuthors";s:19:"submissionChecklist";a:2:{s:2:"en";s:531:"<p>All submissions must meet the following requirements.</p><ul><li>This submission meets the requirements outlined in the <a href="http://localhost/index.php/publicknowledge/about/submissions">Author Guidelines</a>.</li><li>This submission has not been previously posted.</li><li>All references have been checked for accuracy and completeness.</li><li>All tables and figures have been numbered and labeled.</li><li>Permission has been obtained to post all photos, datasets and other material provided with this preprint.</li></ul>";s:5:"fr_CA";s:37:"##default.contextSettings.checklist##";}s:20:"submitWithCategories";b:0;s:20:"supportedFormLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:16:"supportedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:26:"supportedSubmissionLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:15:"themePluginPath";s:7:"default";s:15:"uploadFilesHelp";a:2:{s:2:"en";s:205:"<p>Upload the preprint you would like to share. In addition to the main work, you may wish to upload data sets or other supplementary files that will help researchers understand and evaluate your work.</p>";s:5:"fr_CA";s:317:"<p> Fournir tous les fichiers dont notre équipe éditoriale pourrait avoir besoin pour évaluer votre soumission. En plus du fichier principal, vous pouvez soumettre des ensembles de données, une déclaration relative au conflit d'intérêt ou tout autre fichier potentiellement utile pour nos éditeur.trice.s.</p>";}s:19:"enableGeoUsageStats";s:8:"disabled";s:27:"enableInstitutionUsageStats";b:0;s:16:"isSushiApiPublic";b:1;s:21:"enableAuthorScreening";b:0;s:15:"enabledDoiTypes";a:1:{i:0;s:11:"publication";}s:21:"postedAcknowledgement";b:1;s:9:"enableOai";b:1;s:13:"doiVersioning";b:1;s:13:"customHeaders";a:1:{s:2:"en";s:41:"<meta name="pkp" content="Test metatag.">";}s:17:"searchDescription";a:1:{s:2:"en";s:102:"The Public Knowledge Preprint Server is a preprint service on the subject of public access to science.";}s:12:"abbreviation";a:1:{s:2:"en";s:27:"publicknowledgePub Know Pre";}s:14:"mailingAddress";s:47:"123 456th StreetBurnaby, British ColumbiaCanada";s:12:"supportEmail";s:20:"rvaca@mailinator.com";s:11:"supportName";s:11:"Ramiro Vaca";s:9:"doiPrefix";s:7:"10.1234";s:19:"automaticDoiDeposit";b:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:9:"\0*\0agency";O:43:"APP\plugins\generic\crossref\CrossrefPlugin":4:{s:10:"pluginPath";s:24:"plugins/generic/crossref";s:14:"pluginCategory";s:7:"generic";s:7:"request";N;s:58:"\0APP\plugins\generic\crossref\CrossrefPlugin\0_exportPlugin";N;}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";}
            END,
        };
    }

    /**
     * @copydoc TestCase::setUp()
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Application::get()->getName() === 'omp') {
            $this->markTestSkipped('OMP does not have infrastructural deposit agency support');
        }
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            DepositSubmission::class,
            unserialize($this->getSerializedJobData())
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        // need to mock request so that a valid context information is set and can be retrived
        $this->mockRequest();

        $this->mockGuzzleClient();

        /**
         * @disregard P1013 PHP Intelephense error suppression
         * @see https://github.com/bmewburn/vscode-intelephense/issues/568
         */
        $publicationMock = Mockery::mock(\APP\publication\Publication::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('authors')
            ->andReturn(\Illuminate\Support\LazyCollection::make([new \PKP\author\Author()]))
            ->shouldReceive('getData')
            ->with('issueId')
            ->andReturn(1)
            ->shouldReceive('getData')
            ->with('title')
            ->andReturn(['en' => 'Test title', 'fr_CA' => 'Test title'])
            ->shouldReceive('getTitles')
            ->with('text')
            ->andReturn(['en' => 'Test title', 'fr_CA' => 'Test title'])
            ->shouldReceive('getLocalizedTitle')
            ->withAnyArgs()
            ->andReturn('Test title')
            ->shouldReceive('getData')
            ->with('galleys')
            ->andReturn(
                \Illuminate\Support\LazyCollection::make([Mockery::mock(\PKP\galley\Galley::class)
                    ->makePartial()
                    ->shouldReceive('getDoi')
                    ->withAnyArgs()
                    ->andReturn('10.1234/mq45t6723')
                    ->getMock()])
            )
            ->shouldReceive('getData')
            ->with('doiObject')
            ->andReturn(new \PKP\doi\Doi)
            ->getMock();

        $submissionMock = Mockery::mock(\APP\submission\Submission::class)
            ->makePartial()
            ->shouldReceive([
                'getDatePublished' => \Carbon\Carbon::today()
                    ->startOfYear()
                    ->format('Y-m-d H:i:s'),
                'getStoredPubId' => '10.1234/mq45t6723',
                // 'getData' => '',
                'getCurrentPublication' => $publicationMock,
            ])
            ->shouldReceive('getData')
            ->with('doiObject')
            ->andReturn(new \PKP\doi\Doi())
            ->getMock();

        $submissionDaoMock = Mockery::mock(\APP\submission\DAO::class, [
            new \PKP\services\PKPSchemaService()
        ])
            ->makePartial()
            ->shouldReceive([
                'fromRow' => $submissionMock
            ])
            ->withAnyArgs()
            ->getMock();

        $submissionRepoMock = Mockery::mock(app(SubmissionRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($submissionMock)
            ->set('dao', $submissionDaoMock)
            ->getMock();

        app()->instance(SubmissionRepository::class, $submissionRepoMock);

        $doiRepoMock = Mockery::mock(app(DoiRepository::class))
            ->makePartial()
            ->shouldReceive('edit')
            ->withAnyArgs()
            ->andReturn(null)
            ->getMock();

        app()->instance(DoiRepository::class, $doiRepoMock);

        /** @var \PKP\context\Context $contextMock */
        $contextMock = Mockery::mock(get_class(Application::getContextDAO()->newDataObject()))
            ->makePartial()
            ->shouldReceive([
                'getData' => '',
                'getLocalizedData' => '',
            ])
            ->getMock();
        
        $depositSubmissionMock = new DepositSubmission(
            0, 
            $contextMock,
            new \PKP\tests\support\DoiRegistrationAgency
        );
        
        $depositSubmissionMock->handle();

        $this->expectNotToPerformAssertions();
    }
}
