<?php

/**
 * @file tests/jobs/notifications/StatisticsReportNotifyTest.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for statistics report notification job.
 */

namespace PKP\tests\jobs\notifications;

use Mockery;
use PKP\tests\PKPTestCase;
use APP\user\Repository as UserRepository;
use PKP\jobs\notifications\StatisticsReportNotify;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\CoversClass;

#[RunTestsInSeparateProcesses]
#[CoversClass(StatisticsReportNotify::class)]
class StatisticsReportNotifyTest extends PKPTestCase
{
    /**
     * serializion from OJS 3.4.0
     */
    protected string $serializedJobData = <<<END
    O:45:"PKP\\jobs\\notifications\\StatisticsReportNotify":5:{s:10:"\0*\0userIds";O:29:"Illuminate\\Support\\Collection":2:{s:8:"\0*\0items";a:6:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:5;i:5;i:6;}s:28:"\0*\0escapeWhenCastingToString";b:0;}s:22:"\0*\0notificationManager";O:67:"PKP\\notification\\managerDelegate\\EditorialReportNotificationManager":3:{s:63:"\0PKP\\notification\\NotificationManagerDelegate\0_notificationType";i:16777258;s:77:"\0PKP\\notification\\managerDelegate\\EditorialReportNotificationManager\0_context";O:19:"APP\\journal\\Journal":6:{s:5:"_data";a:73:{s:2:"id";i:1;s:7:"urlPath";s:15:"publicknowledge";s:7:"enabled";b:1;s:3:"seq";i:1;s:13:"primaryLocale";s:2:"en";s:14:"currentIssueId";i:2;s:19:"automaticDoiDeposit";b:0;s:12:"contactEmail";s:20:"rvaca@mailinator.com";s:11:"contactName";s:11:"Ramiro Vaca";s:18:"copyrightYearBasis";s:5:"issue";s:31:"copySubmissionAckPrimaryContact";b:1;s:7:"country";s:2:"IS";s:8:"currency";s:3:"CAD";s:17:"defaultReviewMode";i:2;s:18:"disableSubmissions";b:0;s:15:"doiCreationTime";s:20:"copyEditCreationTime";s:9:"doiPrefix";s:7:"10.1234";s:13:"doiSuffixType";s:7:"default";s:13:"doiVersioning";b:0;s:19:"editorialStatsEmail";b:1;s:14:"emailSignature";s:141:"<br><br>—<br><p>This is an automated message from <a href="http://localhost/index.php/publicknowledge">Journal of Public Knowledge</a>.</p>";s:19:"enableAnnouncements";b:1;s:15:"enabledDoiTypes";a:2:{i:0;s:11:"publication";i:1;s:5:"issue";}s:10:"enableDois";b:1;s:19:"enableGeoUsageStats";s:8:"disabled";s:27:"enableInstitutionUsageStats";b:0;s:9:"enableOai";b:1;s:16:"isSushiApiPublic";b:1;s:12:"itemsPerPage";i:25;s:8:"keywords";s:7:"request";s:14:"mailingAddress";s:49:"123 456th Street Burnaby, British Columbia Canada";s:13:"membershipFee";d:0;s:16:"notifyAllAuthors";b:1;s:12:"numPageLinks";i:10;s:19:"numWeeksPerResponse";i:4;s:17:"numWeeksPerReview";i:4;s:10:"onlineIssn";s:9:"0378-5955";s:17:"paymentPluginName";s:13:"PaypalPayment";s:15:"paymentsEnabled";b:1;s:9:"printIssn";s:9:"0378-5955";s:14:"publicationFee";d:0;s:20:"publisherInstitution";s:24:"Public Knowledge Project";s:18:"purchaseArticleFee";d:0;s:18:"registrationAgency";s:14:"dataciteplugin";s:25:"submissionAcknowledgement";s:10:"allAuthors";s:20:"submitWithCategories";b:0;s:20:"supportedFormLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:16:"supportedLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:26:"supportedSubmissionLocales";a:2:{i:0;s:2:"en";i:1;s:5:"fr_CA";}s:12:"supportEmail";s:20:"rvaca@mailinator.com";s:11:"supportName";s:11:"Ramiro Vaca";s:15:"themePluginPath";s:7:"default";s:12:"abbreviation";a:1:{s:2:"en";s:25:"publicknowledgeJ Pub Know";}s:7:"acronym";a:1:{s:2:"en";s:6:"JPKJPK";}s:16:"authorGuidelines";a:2:{s:2:"en";s:1209:"<p>Authors are invited to make a submission to this journal. All submissions will be assessed by an editor to determine whether they meet the aims and scope of this journal. Those considered to be a good fit will be sent for peer review before determining whether they will be accepted or rejected.</p><p>Before making a submission, authors are responsible for obtaining permission to publish any material included with the submission, such as photos, documents and datasets. All authors identified on the submission must consent to be identified as an author. Where appropriate, research should be approved by an appropriate ethics committee in accordance with the legal requirements of the study's country.</p><p>An editor may desk reject a submission if it does not meet minimum standards of quality. Before submitting, please ensure that the study design and research argument are structured and articulated properly. The title should be concise and the abstract should be able to stand on its own. This will increase the likelihood of reviewers agreeing to review the paper. When you're satisfied that your submission meets this standard, please follow the checklist below to prepare your submission.</p>";s:5:"fr_CA";s:44:"##default.contextSettings.authorGuidelines##";}s:17:"authorInformation";a:2:{s:2:"en";s:586:"Interested in submitting to this journal? We recommend that you review the <a href="http://localhost/index.php/publicknowledge/about">About the Journal</a> page for the journal's section policies, as well as the <a href="http://localhost/index.php/publicknowledge/about/submissions#authorGuidelines">Author Guidelines</a>. Authors need to <a href="http://localhost/index.php/publicknowledge/user/register">register</a> with the journal prior to submitting or, if already registered, can simply <a href="http://localhost/index.php/index/login">log in</a> and begin the five-step process.";s:5:"fr_CA";s:715:"Intéressé-e à soumettre à cette revue ? Nous vous recommandons de consulter les politiques de rubrique de la revue à la page <a href="http://localhost/index.php/publicknowledge/about">À propos de la revue</a> ainsi que les <a href="http://localhost/index.php/publicknowledge/about/submissions#authorGuidelines">Directives aux auteurs</a>. Les auteurs-es doivent <a href="http://localhost/index.php/publicknowledge/user/register">s'inscrire</a> auprès de la revue avant de présenter une soumission, ou s'ils et elles sont déjà inscrits-es, simplement <a href="http://localhost/index.php/publicknowledge/login">ouvrir une session</a> et accéder au tableau de bord pour commencer les 5 étapes du processus.";}s:19:"beginSubmissionHelp";a:2:{s:2:"en";s:611:"<p>Thank you for submitting to the Journal of Public Knowledge. You will be asked to upload files, identify co-authors, and provide information such as the title and abstract.<p><p>Please read our <a href="http://localhost/index.php/publicknowledge/about/submissions" target="_blank">Submission Guidelines</a> if you have not done so already. When filling out the forms, provide as many details as possible in order to help our editors evaluate your work.</p><p>Once you begin, you can save your submission and come back to it later. You will be able to review and correct any information before you submit.</p>";s:5:"fr_CA";s:42:"##default.submission.step.beforeYouBegin##";}s:14:"clockssLicense";a:2:{s:2:"en";s:271:"This journal utilizes the CLOCKSS system to create a distributed archiving system among participating libraries and permits those libraries to create permanent archives of the journal for purposes of preservation and restoration. <a href="https://clockss.org">More...</a>";s:5:"fr_CA";s:315:"Cette revue utilise le système CLOCKSS pour créer un système d'archivage distribué parmi les bibliothèques participantes et permet à ces bibliothèques de créer des archives permanentes de la revue à des fins de conservation et de reconstitution. <a href="https://clockss.org">En apprendre davantage... </a>";}s:16:"contributorsHelp";a:2:{s:2:"en";s:504:"<p>Add details for all of the contributors to this submission. Contributors added here will be sent an email confirmation of the submission, as well as a copy of all editorial decisions recorded against this submission.</p><p>If a contributor can not be contacted by email, because they must remain anonymous or do not have an email account, please do not enter a fake email address. You can add information about this contributor in a message to the editor at a later step in the submission process.</p>";s:5:"fr_CA";s:40:"##default.submission.step.contributors##";}s:13:"customHeaders";a:1:{s:2:"en";s:41:"<meta name="pkp" content="Test metatag.">";}s:11:"description";a:2:{s:2:"en";s:123:"<p>The Journal of Public Knowledge is a peer-reviewed quarterly publication on the subject of public access to science.</p>";s:5:"fr_CA";s:146:"<p>Le Journal de Public Knowledge est une publication trimestrielle évaluée par les pairs sur le thème de l'accès du public à la science.</p>";}s:11:"detailsHelp";a:2:{s:2:"en";s:92:"<p>Please provide the following details to help us manage your submission in our system.</p>";s:5:"fr_CA";s:35:"##default.submission.step.details##";}s:17:"forTheEditorsHelp";a:2:{s:2:"en";s:278:"<p>Please provide the following details in order to help our editorial team manage your submission.</p><p>When entering metadata, provide entries that you think would be most helpful to the person managing your submission. This information can be changed before publication.</p>";s:5:"fr_CA";s:41:"##default.submission.step.forTheEditors##";}s:20:"librarianInformation";a:2:{s:2:"en";s:361:"We encourage research librarians to list this journal among their library's electronic journal holdings. As well, it may be worth noting that this journal's open source publishing system is suitable for libraries to host for their faculty members to use with journals they are involved in editing (see <a href="https://pkp.sfu.ca/ojs">Open Journal Systems</a>).";s:5:"fr_CA";s:434:"Nous incitons les bibliothécaires à lister cette revue dans leur fonds de revues numériques. Aussi, il peut être pertinent de mentionner que ce système de publication en libre accès est conçu pour être hébergé par les bibliothèques de recherche pour que les membres de leurs facultés l'utilisent avec les revues dans lesquelles elles ou ils sont impliqués (voir <a href="https://pkp.sfu.ca/ojs">Open Journal Systems</a>).";}s:13:"lockssLicense";a:2:{s:2:"en";s:273:"This journal utilizes the LOCKSS system to create a distributed archiving system among participating libraries and permits those libraries to create permanent archives of the journal for purposes of preservation and restoration. <a href="https://www.lockss.org">More...</a>";s:5:"fr_CA";s:314:"Cette revue utilise le système LOCKSS pour créer un système de distribution des archives parmi les bibliothèques participantes et afin de permettre à ces bibliothèques de créer des archives permanentes pour fins de préservation et de restauration. <a href="https://lockss.org">En apprendre davantage...</a>";}s:4:"name";a:2:{s:2:"en";s:27:"Journal of Public Knowledge";s:5:"fr_CA";s:36:"Journal de la connaissance du public";}s:16:"openAccessPolicy";a:2:{s:2:"en";s:176:"This journal provides immediate open access to its content on the principle that making research freely available to the public supports a greater global exchange of knowledge.";s:5:"fr_CA";s:217:"Cette revue fournit le libre accès immédiat à son contenu se basant sur le principe que rendre la recherche disponible au public gratuitement facilite un plus grand échange du savoir, à l'échelle de la planète.";}s:16:"privacyStatement";a:2:{s:2:"en";s:206:"<p>The names and email addresses entered in this journal site will be used exclusively for the stated purposes of this journal and will not be made available for any other purpose or to any other party.</p>";s:5:"fr_CA";s:193:"<p>Les noms et courriels saisis dans le site de cette revue seront utilisés exclusivement aux fins indiquées par cette revue et ne serviront à aucune autre fin, ni à toute autre partie.</p>";}s:17:"readerInformation";a:2:{s:2:"en";s:654:"We encourage readers to sign up for the publishing notification service for this journal. Use the <a href="http://localhost/index.php/publicknowledge/user/register">Register</a> link at the top of the home page for the journal. This registration will result in the reader receiving the Table of Contents by email for each new issue of the journal. This list also allows the journal to claim a certain level of support or readership. See the journal's <a href="http://localhost/index.php/publicknowledge/about/submissions#privacyStatement">Privacy Statement</a>, which assures readers that their name and email address will not be used for other purposes.";s:5:"fr_CA";s:716:"Nous invitons les lecteurs-trices à s'inscrire pour recevoir les avis de publication de cette revue. Utiliser le lien <a href="http://localhost/index.php/publicknowledge/user/register">S'inscrire</a> en haut de la page d'accueil de la revue. Cette inscription permettra au,à la lecteur-trice de recevoir par courriel le sommaire de chaque nouveau numéro de la revue. Cette liste permet aussi à la revue de revendiquer un certain niveau de soutien ou de lectorat. Voir la <a href="http://localhost/index.php/publicknowledge/about/submissions#privacyStatement">Déclaration de confidentialité</a> de la revue qui certifie aux lecteurs-trices que leur nom et leur courriel ne seront pas utilisés à d'autres fins.";}s:10:"reviewHelp";a:2:{s:2:"en";s:368:"<p>Review the information you have entered before you complete your submission. You can change any of the details displayed here by clicking the edit button at the top of each section.</p><p>Once you complete your submission, a member of our editorial team will be assigned to review it. Please ensure the details you have entered here are as accurate as possible.</p>";s:5:"fr_CA";s:34:"##default.submission.step.review##";}s:17:"searchDescription";a:1:{s:2:"en";s:116:"The Journal of Public Knowledge is a peer-reviewed quarterly publication on the subject of public access to science.";}s:19:"submissionChecklist";a:2:{s:2:"en";s:591:"<p>All submissions must meet the following requirements.</p><ul><li>This submission meets the requirements outlined in the <a href="http://localhost/index.php/publicknowledge/about/submissions">Author Guidelines</a>.</li><li>This submission has not been previously published, nor is it before another journal for consideration.</li><li>All references have been checked for accuracy and completeness.</li><li>All tables and figures have been numbered and labeled.</li><li>Permission has been obtained to publish all photos, datasets and other material provided with this submission.</li></ul>";s:5:"fr_CA";s:37:"##default.contextSettings.checklist##";}s:15:"uploadFilesHelp";a:2:{s:2:"en";s:249:"<p>Provide any files our editorial team may need to evaluate your submission. In addition to the main work, you may wish to submit data sets, conflict of interest statements, or other supplementary files if these will be helpful for our editors.</p>";s:5:"fr_CA";s:39:"##default.submission.step.uploadFiles##";}}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:77:"\0PKP\\notification\\managerDelegate\\EditorialReportNotificationManager\0_request";O:16:"APP\\core\\Request":10:{s:7:"_router";O:19:"APP\\core\\PageRouter":10:{s:15:"\0*\0_application";O:20:"APP\\core\\Application":2:{s:15:"enabledProducts";a:1:{i:0;a:10:{s:16:"plugins.metadata";a:1:{s:4:"dc11";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:16:"plugins.metadata";s:7:"product";s:4:"dc11";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:14:"plugins.blocks";a:1:{s:14:"languageToggle";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:14:"plugins.blocks";s:7:"product";s:14:"languageToggle";s:16:"productClassName";s:25:"LanguageToggleBlockPlugin";s:8:"lazyLoad";i:1;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:16:"plugins.gateways";a:1:{s:8:"resolver";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:16:"plugins.gateways";s:7:"product";s:8:"resolver";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:15:"plugins.generic";a:5:{s:8:"datacite";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:2;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.generic";s:7:"product";s:8:"datacite";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:10:"usageEvent";O:16:"PKP\site\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.generic";s:7:"product";s:10:"usageEvent";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:5:"acron";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:3;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.generic";s:7:"product";s:5:"acron";s:16:"productClassName";s:11:"AcronPlugin";s:8:"lazyLoad";i:1;s:8:"sitewide";i:1;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:7:"tinymce";O:16:"PKP\site\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.generic";s:7:"product";s:7:"tinymce";s:16:"productClassName";s:13:"TinyMCEPlugin";s:8:"lazyLoad";i:1;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:8:"crossref";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:3;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.generic";s:7:"product";s:8:"crossref";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:20:"plugins.importexport";a:4:{s:5:"users";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:20:"plugins.importexport";s:7:"product";s:5:"users";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:6:"native";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:20:"plugins.importexport";s:7:"product";s:6:"native";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:6:"pubmed";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:20:"plugins.importexport";s:7:"product";s:6:"pubmed";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:4:"doaj";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:1;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:33";s:7:"current";i:1;s:11:"productType";s:20:"plugins.importexport";s:7:"product";s:4:"doaj";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:26:"plugins.oaiMetadataFormats";a:4:{s:7:"rfc1807";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:26:"plugins.oaiMetadataFormats";s:7:"product";s:7:"rfc1807";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:7:"marcxml";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:26:"plugins.oaiMetadataFormats";s:7:"product";s:7:"marcxml";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:4:"marc";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:26:"plugins.oaiMetadataFormats";s:7:"product";s:4:"marc";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:2:"dc";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:26:"plugins.oaiMetadataFormats";s:7:"product";s:2:"dc";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:17:"plugins.paymethod";a:2:{s:6:"manual";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:17:"plugins.paymethod";s:7:"product";s:6:"manual";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:6:"paypal";O:16:"PKP\site\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:17:"plugins.paymethod";s:7:"product";s:6:"paypal";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:15:"plugins.reports";a:4:{s:13:"counterReport";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:1;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.reports";s:7:"product";s:13:"counterReport";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:8:"articles";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.reports";s:7:"product";s:8:"articles";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:12:"reviewReport";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:2;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.reports";s:7:"product";s:12:"reviewReport";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}s:13:"subscriptions";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:15:"plugins.reports";s:7:"product";s:13:"subscriptions";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:14:"plugins.themes";a:1:{s:7:"default";O:16:"PKP\\site\\Version":6:{s:5:"_data";a:11:{s:5:"major";i:1;s:5:"minor";i:0;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:07";s:7:"current";i:1;s:11:"productType";s:14:"plugins.themes";s:7:"product";s:7:"default";s:16:"productClassName";s:18:"DefaultThemePlugin";s:8:"lazyLoad";i:1;s:8:"sitewide";i:0;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}s:4:"core";a:1:{s:4:"ojs2";O:16:"PKP\site\Version":6:{s:5:"_data";a:11:{s:5:"major";i:3;s:5:"minor";i:4;s:8:"revision";i:0;s:5:"build";i:0;s:13:"dateInstalled";s:19:"2023-02-28 20:19:00";s:7:"current";i:1;s:11:"productType";s:4:"core";s:7:"product";s:4:"ojs2";s:16:"productClassName";s:0:"";s:8:"lazyLoad";i:0;s:8:"sitewide";i:1;}s:20:"_hasLoadableAdapters";b:0;s:27:"_metadataExtractionAdapters";a:0:{}s:25:"_extractionAdaptersLoaded";b:0;s:26:"_metadataInjectionAdapters";a:0:{}s:24:"_injectionAdaptersLoaded";b:0;}}}}s:11:"allProducts";N;}s:14:"\0*\0_dispatcher";O:19:"PKP\\core\\Dispatcher":5:{s:12:"_application";r:141;s:12:"_routerNames";a:3:{s:3:"api";s:19:"\\PKP\\core\\APIRouter";s:9:"component";s:28:"\PKP\core\PKPComponentRouter";s:4:"page";s:20:"\\APP\\core\\PageRouter";}s:16:"_routerInstances";a:3:{s:3:"api";O:18:"PKP\\core\\APIRouter":6:{s:15:"\0*\0_application";r:141;s:14:"\0*\0_dispatcher";r:587;s:15:"\0*\0_contextPath";N;s:8:"_context";N;s:8:"_handler";N;s:9:"_indexUrl";s:80:"http://ojs-stable-3_4_0.test/Users/abir/.composer/vendor/laravel/valet/index.php";}s:9:"component";O:27:"PKP\core\PKPComponentRouter":10:{s:15:"\0*\0_application";r:141;s:14:"\0*\0_dispatcher";r:587;s:15:"\0*\0_contextPath";s:5:"index";s:8:"_context";N;s:8:"_handler";N;s:9:"_indexUrl";s:80:"http://ojs-stable-3_4_0.test/Users/abir/.composer/vendor/laravel/valet/index.php";s:10:"_component";N;s:3:"_op";N;s:24:"_rpcServiceEndpointParts";b:0;s:19:"_rpcServiceEndpoint";b:0;}s:4:"page";R:140;}s:7:"_router";R:140;s:20:"_requestCallbackHack";r:139;}s:15:"\0*\0_contextPath";s:5:"index";s:8:"_context";N;s:8:"_handler";O:28:"APP\\pages\\index\\IndexHandler":9:{s:12:"\0*\0_apiToken";N;s:3:"_id";s:0:"";s:11:"_dispatcher";r:587;s:7:"_checks";a:0:{}s:16:"_roleAssignments";a:0:{}s:29:"_authorizationDecisionManager";O:55:"PKP\\security\\authorization\\AuthorizationDecisionManager":3:{s:14:"_rootPolicySet";O:36:"PKP\\security\\authorization\\PolicySet":3:{s:9:"_policies";a:3:{i:0;O:45:"PKP\\security\\authorization\\AllowedHostsPolicy":3:{s:7:"_advice";a:1:{i:2;a:3:{i:0;r:624;i:1;s:10:"callOnDeny";i:2;a:0:{}}}s:18:"_authorizedContext";a:0:{}s:8:"_request";r:139;}i:1;O:38:"PKP\\security\\authorization\\HttpsPolicy":3:{s:7:"_advice";a:1:{i:2;a:3:{i:0;r:139;i:1;s:11:"redirectSSL";i:2;a:0:{}}}s:18:"_authorizedContext";R:630;s:8:"_request";r:139;}i:2;O:53:"PKP\\security\\authorization\\RestrictedSiteAccessPolicy":4:{s:7:"_advice";a:1:{i:1;s:39:"user.authorization.restrictedSiteAccess";}s:18:"_authorizedContext";R:630;s:62:"\0PKP\\security\\authorization\\RestrictedSiteAccessPolicy\0_router";r:140;s:63:"\0PKP\\security\\authorization\\RestrictedSiteAccessPolicy\0_request";r:139;}}s:19:"_combiningAlgorithm";i:1;s:24:"_effectIfNoPolicyApplies";i:1;}s:22:"_authorizationMessages";a:0:{}s:18:"_authorizedContext";R:630;}s:22:"_enforceRestrictedSite";b:1;s:23:"_roleAssignmentsChecked";b:0;s:14:"_isBackendPage";b:0;}s:9:"_indexUrl";s:80:"http://ojs-stable-3_4_0.test/Users/abir/.composer/vendor/laravel/valet/index.php";s:18:"_installationPages";a:4:{i:0;s:7:"install";i:1;s:4:"help";i:2;s:6:"header";i:3;s:7:"sidebar";}s:5:"_page";s:0:"";s:3:"_op";s:5:"index";s:14:"_cacheFilename";s:78:"/Users/abir/Sites/code/ojs-main/cache/wc-ff7472e8e7ded5f1751e73a64fa00139.html";}s:11:"_dispatcher";r:587;s:12:"_requestVars";a:0:{}s:9:"_basePath";s:42:"/Users/abir/.composer/vendor/laravel/valet";s:12:"_requestPath";s:70:"/Applications/Tinkerwell.app/Contents/Resources/tinkerwell/tinker.phar";s:21:"_isRestfulUrlsEnabled";b:0;s:11:"_serverHost";s:21:"ojs-stable-3_4_0.test";s:9:"_protocol";s:4:"http";s:6:"_isBot";b:1;s:10:"_userAgent";s:117:"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36";}}s:10:"connection";s:8:"database";s:5:"queue";s:5:"queue";s:7:"batchId";s:36:"9c872d03-b95f-47f8-ab10-78d2772697d6";}
    END;

    /**
     * Test job is a proper instance
     */
    public function testUnserializationGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            StatisticsReportNotify::class,
            unserialize($this->serializedJobData)
        );
    }

    /**
     * Ensure that a serialized job can be unserialized and executed
     */
    public function testRunSerializedJob(): void
    {
        /** @var StatisticsReportNotify $statisticsReportNotifyJob */
        $statisticsReportNotifyJob = unserialize($this->serializedJobData);

        $userRepoMock = Mockery::mock(app(UserRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn(new \PKP\user\User)
            ->getMock();
        
        app()->instance(UserRepository::class, $userRepoMock);

        $this->assertNull($statisticsReportNotifyJob->handle());
    }
}
