<?php

/**
 * @file tests/jobs/orcid/SendAuthorMailTest.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Tests for the author ORCID verification email job.
 */

namespace PKP\tests\jobs\orcid;

use APP\publication\Publication;
use APP\publication\Repository as PublicationRepository;
use APP\submission\Repository as SubmissionRepository;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PKP\citation\CitationDAO;
use PKP\db\DAORegistry;
use PKP\jobs\orcid\SendAuthorMail;
use PKP\services\PKPSchemaService;
use PKP\site\Site;
use PKP\site\SiteDAO;
use PKP\submission\SubmissionAgencyDAO;
use PKP\submission\SubmissionDisciplineDAO;
use PKP\submission\SubmissionKeywordDAO;
use PKP\submission\SubmissionSubjectDAO;
use PKP\tests\PKPTestCase;

class SendAuthorMailTest extends PKPTestCase
{

    /**
     * base64_encoded serialization from OJS 3.5.0
     */
    protected string $serializedJobData = "TzoyOToiUEtQXGpvYnNcb3JjaWRcU2VuZEF1dGhvck1haWwiOjM6e3M6Mzc6IgBQS1Bcam9ic1xvcmNpZFxTZW5kQXV0aG9yTWFpbABhdXRob3IiO086MTc6IkFQUFxhdXRob3JcQXV0aG9yIjo3OntzOjU6Il9kYXRhIjthOjExOntzOjI6ImlkIjtpOjE7czo1OiJlbWFpbCI7czoyNToiYW13YW5kZW5nYUBtYWlsaW5hdG9yLmNvbSI7czoxNToiaW5jbHVkZUluQnJvd3NlIjtiOjE7czoxMzoicHVibGljYXRpb25JZCI7aToxO3M6Mzoic2VxIjtpOjA7czoxMToidXNlckdyb3VwSWQiO2k6MTQ7czo3OiJjb3VudHJ5IjtzOjI6IlpBIjtzOjExOiJhZmZpbGlhdGlvbiI7YToxOntzOjI6ImVuIjtzOjIzOiJVbml2ZXJzaXR5IG9mIENhcGUgVG93biI7fXM6MTA6ImZhbWlseU5hbWUiO2E6MTp7czoyOiJlbiI7czo5OiJNd2FuZGVuZ2EiO31zOjk6ImdpdmVuTmFtZSI7YToxOntzOjI6ImVuIjtzOjQ6IkFsYW4iO31zOjY6ImxvY2FsZSI7czoyOiJlbiI7fXM6MjA6Il9oYXNMb2FkYWJsZUFkYXB0ZXJzIjtiOjA7czoyNzoiX21ldGFkYXRhRXh0cmFjdGlvbkFkYXB0ZXJzIjthOjA6e31zOjI1OiJfZXh0cmFjdGlvbkFkYXB0ZXJzTG9hZGVkIjtiOjA7czoyNjoiX21ldGFkYXRhSW5qZWN0aW9uQWRhcHRlcnMiO2E6MDp7fXM6MjQ6Il9pbmplY3Rpb25BZGFwdGVyc0xvYWRlZCI7YjowO3M6MTM6Il9sb2NhbGVzVGFibGUiO2E6OTp7czoxMToiYmVAY3lyaWxsaWMiO3M6MjoiYmUiO3M6MjoiYnMiO3M6NzoiYnNfTGF0biI7czo1OiJmcl9GUiI7czoyOiJmciI7czoyOiJuYiI7czo1OiJuYl9OTyI7czoxMToic3JAY3lyaWxsaWMiO3M6Nzoic3JfQ3lybCI7czo4OiJzckBsYXRpbiI7czo3OiJzcl9MYXRuIjtzOjExOiJ1ekBjeXJpbGxpYyI7czoyOiJ1eiI7czo4OiJ1ekBsYXRpbiI7czo3OiJ1el9MYXRuIjtzOjU6InpoX0NOIjtzOjc6InpoX0hhbnMiO319czozODoiAFBLUFxqb2JzXG9yY2lkXFNlbmRBdXRob3JNYWlsAGNvbnRleHQiO086MTk6IkFQUFxqb3VybmFsXEpvdXJuYWwiOjc6e3M6NToiX2RhdGEiO2E6Nzk6e3M6MjoiaWQiO2k6MTtzOjc6InVybFBhdGgiO3M6MTU6InB1YmxpY2tub3dsZWRnZSI7czo3OiJlbmFibGVkIjtiOjE7czozOiJzZXEiO2k6MTtzOjEzOiJwcmltYXJ5TG9jYWxlIjtzOjI6ImVuIjtzOjE0OiJjdXJyZW50SXNzdWVJZCI7aToxO3M6NzoiYWNyb255bSI7YToxOntzOjI6ImVuIjtzOjY6IkpQS0pQSyI7fXM6MTY6ImF1dGhvckd1aWRlbGluZXMiO2E6Mjp7czoyOiJlbiI7czoxMjA5OiI8cD5BdXRob3JzIGFyZSBpbnZpdGVkIHRvIG1ha2UgYSBzdWJtaXNzaW9uIHRvIHRoaXMgam91cm5hbC4gQWxsIHN1Ym1pc3Npb25zIHdpbGwgYmUgYXNzZXNzZWQgYnkgYW4gZWRpdG9yIHRvIGRldGVybWluZSB3aGV0aGVyIHRoZXkgbWVldCB0aGUgYWltcyBhbmQgc2NvcGUgb2YgdGhpcyBqb3VybmFsLiBUaG9zZSBjb25zaWRlcmVkIHRvIGJlIGEgZ29vZCBmaXQgd2lsbCBiZSBzZW50IGZvciBwZWVyIHJldmlldyBiZWZvcmUgZGV0ZXJtaW5pbmcgd2hldGhlciB0aGV5IHdpbGwgYmUgYWNjZXB0ZWQgb3IgcmVqZWN0ZWQuPC9wPjxwPkJlZm9yZSBtYWtpbmcgYSBzdWJtaXNzaW9uLCBhdXRob3JzIGFyZSByZXNwb25zaWJsZSBmb3Igb2J0YWluaW5nIHBlcm1pc3Npb24gdG8gcHVibGlzaCBhbnkgbWF0ZXJpYWwgaW5jbHVkZWQgd2l0aCB0aGUgc3VibWlzc2lvbiwgc3VjaCBhcyBwaG90b3MsIGRvY3VtZW50cyBhbmQgZGF0YXNldHMuIEFsbCBhdXRob3JzIGlkZW50aWZpZWQgb24gdGhlIHN1Ym1pc3Npb24gbXVzdCBjb25zZW50IHRvIGJlIGlkZW50aWZpZWQgYXMgYW4gYXV0aG9yLiBXaGVyZSBhcHByb3ByaWF0ZSwgcmVzZWFyY2ggc2hvdWxkIGJlIGFwcHJvdmVkIGJ5IGFuIGFwcHJvcHJpYXRlIGV0aGljcyBjb21taXR0ZWUgaW4gYWNjb3JkYW5jZSB3aXRoIHRoZSBsZWdhbCByZXF1aXJlbWVudHMgb2YgdGhlIHN0dWR5J3MgY291bnRyeS48L3A+PHA+QW4gZWRpdG9yIG1heSBkZXNrIHJlamVjdCBhIHN1Ym1pc3Npb24gaWYgaXQgZG9lcyBub3QgbWVldCBtaW5pbXVtIHN0YW5kYXJkcyBvZiBxdWFsaXR5LiBCZWZvcmUgc3VibWl0dGluZywgcGxlYXNlIGVuc3VyZSB0aGF0IHRoZSBzdHVkeSBkZXNpZ24gYW5kIHJlc2VhcmNoIGFyZ3VtZW50IGFyZSBzdHJ1Y3R1cmVkIGFuZCBhcnRpY3VsYXRlZCBwcm9wZXJseS4gVGhlIHRpdGxlIHNob3VsZCBiZSBjb25jaXNlIGFuZCB0aGUgYWJzdHJhY3Qgc2hvdWxkIGJlIGFibGUgdG8gc3RhbmQgb24gaXRzIG93bi4gVGhpcyB3aWxsIGluY3JlYXNlIHRoZSBsaWtlbGlob29kIG9mIHJldmlld2VycyBhZ3JlZWluZyB0byByZXZpZXcgdGhlIHBhcGVyLiBXaGVuIHlvdSdyZSBzYXRpc2ZpZWQgdGhhdCB5b3VyIHN1Ym1pc3Npb24gbWVldHMgdGhpcyBzdGFuZGFyZCwgcGxlYXNlIGZvbGxvdyB0aGUgY2hlY2tsaXN0IGJlbG93IHRvIHByZXBhcmUgeW91ciBzdWJtaXNzaW9uLjwvcD4iO3M6NToiZnJfQ0EiO3M6MTUxNjoiPHA+TGVzIGF1dGV1ci5lLnMgc29udCBpbnZpdMOpLmUucyDDoCBzb3VtZXR0cmUgdW4gYXJ0aWNsZSDDoCBjZXR0ZSByZXZ1ZS4gVG91dGVzIGxlcyBzb3VtaXNzaW9ucyBzZXJvbnQgw6l2YWx1w6llcyBwYXIgdW4uZSByw6lkYWN0ZXVyLnRyaWNlIGFmaW4gZGUgZMOpdGVybWluZXIgc2kgZWxsZXMgY29ycmVzcG9uZGVudCBhdXggb2JqZWN0aWZzIGV0IGF1IGNoYW1wIGQnYXBwbGljYXRpb24gZGUgY2V0dGUgcmV2dWUuIENldXggY29uc2lkw6lyw6lzIGNvbW1lIMOpdGFudCBhcHByb3ByacOpcyBzZXJvbnQgZW52b3nDqXMgw6AgZGVzIHBhaXJzIHBvdXIgZXhhbWVuIGF2YW50IGRlIGTDqWNpZGVyIHMnaWxzIHNlcm9udCBhY2NlcHTDqXMgb3UgcmVqZXTDqXMuPC9wPjxwPkF2YW50IGRlIHNvdW1ldHRyZSBsZXVyIGFydGljbGUsIGxlcyBhdXRldXIuZS5zIHNvbnQgcmVzcG9uc2FibGVzIGQnb2J0ZW5pciBsJ2F1dG9yaXNhdGlvbiBkZSBwdWJsaWVyIHRvdXQgbWF0w6lyaWVsIGluY2x1cyBkYW5zIGxhIHNvdW1pc3Npb24sIHRlbHMgcXVlIGRlcyBwaG90b3MsIGRlcyBkb2N1bWVudHMgZXQgZGVzIGVuc2VtYmxlcyBkZSBkb25uw6llcy4gVG91cyBsZXMgYXV0ZXVyLmUucyBpZGVudGlmacOpLmUucyBkYW5zIGxhIHNvdW1pc3Npb24gZG9pdmVudCBjb25zZW50aXIgw6Agw6p0cmUgaWRlbnRpZmnDqS5lLnMgY29tbWUgYXV0ZXVyLmUucy4gTG9yc3F1ZSBjZWxhIGVzdCBhcHByb3ByacOpLCBsYSByZWNoZXJjaGUgZG9pdCDDqnRyZSBhcHByb3V2w6llIHBhciB1biBjb21pdMOpIGQnw6l0aGlxdWUgYXBwcm9wcmnDqSBjb25mb3Jtw6ltZW50IGF1eCBleGlnZW5jZXMgbMOpZ2FsZXMgZHUgcGF5cyBkZSBsJ8OpdHVkZS48L3A+PHA+VW4uZSByw6lkYWN0ZXVyLnRyaWNlIHBldXQgcmVqZXRlciB1bmUgc291bWlzc2lvbiBzYW5zIGV4YW1lbiBhcHByb2ZvbmRpIHMnaWwgbmUgcsOpcG9uZCBwYXMgYXV4IG5vcm1lcyBtaW5pbWFsZXMgZGUgcXVhbGl0w6kuIEF2YW50IGRlIHNvdW1ldHRyZSB2b3RyZSBhcnRpY2xlLCB2ZXVpbGxleiB2b3VzIGFzc3VyZXIgcXVlIGxhIGNvbmNlcHRpb24gZGUgbCfDqXR1ZGUgZXQgbCdhcmd1bWVudCBkZSByZWNoZXJjaGUgc29udCBzdHJ1Y3R1csOpcyBldCBhcnRpY3Vsw6lzIGNvcnJlY3RlbWVudC4gTGUgdGl0cmUgZG9pdCDDqnRyZSBjb25jaXMgZXQgbGUgcsOpc3Vtw6kgZG9pdCBwb3V2b2lyIMOqdHJlIGNvbXByaXMgaW5kw6lwZW5kYW1tZW50IGR1IHJlc3RlIGR1IHRleHRlLiBDZWxhIGF1Z21lbnRlcmEgbGEgcHJvYmFiaWxpdMOpIHF1ZSBsZXMgw6l2YWx1YXRldXIudHJpY2UucyBhY2NlcHRlbnQgZCdleGFtaW5lciBsJ2FydGljbGUuIExvcnNxdWUgdm91cyDDqnRlcyBjb25maWFudC5lIHF1ZSB2b3RyZSBhcnRpY2xlIHLDqXBvbmQgw6AgY2VzIGV4aWdlbmNlcywgdm91cyBwb3V2ZXogc3VpdnJlIGxhIGxpc3RlIGRlIGNvbnRyw7RsZSBjaS1kZXNzb3VzIHBvdXIgcHLDqXBhcmVyIHZvdHJlIHNvdW1pc3Npb24uPC9wPiI7fXM6MTc6ImF1dGhvckluZm9ybWF0aW9uIjthOjI6e3M6MjoiZW4iO3M6NTg2OiJJbnRlcmVzdGVkIGluIHN1Ym1pdHRpbmcgdG8gdGhpcyBqb3VybmFsPyBXZSByZWNvbW1lbmQgdGhhdCB5b3UgcmV2aWV3IHRoZSA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvYWJvdXQiPkFib3V0IHRoZSBKb3VybmFsPC9hPiBwYWdlIGZvciB0aGUgam91cm5hbCdzIHNlY3Rpb24gcG9saWNpZXMsIGFzIHdlbGwgYXMgdGhlIDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL3B1YmxpY2tub3dsZWRnZS9hYm91dC9zdWJtaXNzaW9ucyNhdXRob3JHdWlkZWxpbmVzIj5BdXRob3IgR3VpZGVsaW5lczwvYT4uIEF1dGhvcnMgbmVlZCB0byA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvdXNlci9yZWdpc3RlciI+cmVnaXN0ZXI8L2E+IHdpdGggdGhlIGpvdXJuYWwgcHJpb3IgdG8gc3VibWl0dGluZyBvciwgaWYgYWxyZWFkeSByZWdpc3RlcmVkLCBjYW4gc2ltcGx5IDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL2luZGV4L2xvZ2luIj5sb2cgaW48L2E+IGFuZCBiZWdpbiB0aGUgZml2ZS1zdGVwIHByb2Nlc3MuIjtzOjU6ImZyX0NBIjtzOjcxNToiSW50w6lyZXNzw6ktZSDDoCBzb3VtZXR0cmUgw6AgY2V0dGUgcmV2dWUgPyBOb3VzIHZvdXMgcmVjb21tYW5kb25zIGRlIGNvbnN1bHRlciBsZXMgcG9saXRpcXVlcyBkZSBydWJyaXF1ZSBkZSBsYSByZXZ1ZSDDoCBsYSBwYWdlIDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL3B1YmxpY2tub3dsZWRnZS9hYm91dCI+w4AgcHJvcG9zIGRlIGxhIHJldnVlPC9hPiBhaW5zaSBxdWUgbGVzIDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL3B1YmxpY2tub3dsZWRnZS9hYm91dC9zdWJtaXNzaW9ucyNhdXRob3JHdWlkZWxpbmVzIj5EaXJlY3RpdmVzIGF1eCBhdXRldXJzPC9hPi4gTGVzIGF1dGV1cnMtZXMgZG9pdmVudCA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvdXNlci9yZWdpc3RlciI+cydpbnNjcmlyZTwvYT4gYXVwcsOocyBkZSBsYSByZXZ1ZSBhdmFudCBkZSBwcsOpc2VudGVyIHVuZSBzb3VtaXNzaW9uLCBvdSBzJ2lscyBldCBlbGxlcyBzb250IGTDqWrDoCBpbnNjcml0cy1lcywgc2ltcGxlbWVudCA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvbG9naW4iPm91dnJpciB1bmUgc2Vzc2lvbjwvYT4gZXQgYWNjw6lkZXIgYXUgdGFibGVhdSBkZSBib3JkIHBvdXIgY29tbWVuY2VyIGxlcyA1IMOpdGFwZXMgZHUgcHJvY2Vzc3VzLiI7fXM6MTk6ImJlZ2luU3VibWlzc2lvbkhlbHAiO2E6Mjp7czoyOiJlbiI7czo2MTE6IjxwPlRoYW5rIHlvdSBmb3Igc3VibWl0dGluZyB0byB0aGUgSm91cm5hbCBvZiBQdWJsaWMgS25vd2xlZGdlLiBZb3Ugd2lsbCBiZSBhc2tlZCB0byB1cGxvYWQgZmlsZXMsIGlkZW50aWZ5IGNvLWF1dGhvcnMsIGFuZCBwcm92aWRlIGluZm9ybWF0aW9uIHN1Y2ggYXMgdGhlIHRpdGxlIGFuZCBhYnN0cmFjdC48cD48cD5QbGVhc2UgcmVhZCBvdXIgPGEgaHJlZj0iaHR0cDovL2xvY2FsaG9zdC9pbmRleC5waHAvcHVibGlja25vd2xlZGdlL2Fib3V0L3N1Ym1pc3Npb25zIiB0YXJnZXQ9Il9ibGFuayI+U3VibWlzc2lvbiBHdWlkZWxpbmVzPC9hPiBpZiB5b3UgaGF2ZSBub3QgZG9uZSBzbyBhbHJlYWR5LiBXaGVuIGZpbGxpbmcgb3V0IHRoZSBmb3JtcywgcHJvdmlkZSBhcyBtYW55IGRldGFpbHMgYXMgcG9zc2libGUgaW4gb3JkZXIgdG8gaGVscCBvdXIgZWRpdG9ycyBldmFsdWF0ZSB5b3VyIHdvcmsuPC9wPjxwPk9uY2UgeW91IGJlZ2luLCB5b3UgY2FuIHNhdmUgeW91ciBzdWJtaXNzaW9uIGFuZCBjb21lIGJhY2sgdG8gaXQgbGF0ZXIuIFlvdSB3aWxsIGJlIGFibGUgdG8gcmV2aWV3IGFuZCBjb3JyZWN0IGFueSBpbmZvcm1hdGlvbiBiZWZvcmUgeW91IHN1Ym1pdC48L3A+IjtzOjU6ImZyX0NBIjtzOjc2MjoiPHA+TWVyY2kgZGUgdm90cmUgc291bWlzc2lvbiDDoCBsYSByZXZ1ZSBKb3VybmFsIG9mIFB1YmxpYyBLbm93bGVkZ2UuIElsIHZvdXMgc2VyYSBkZW1hbmTDqSBkZSB0w6lsw6l2ZXJzZXIgZGVzIGZpY2hpZXJzLCBpZGVudGlmaWVyIGRlcyBjby1hdXRldXIudHJpY2UucyBldCBmb3VybmlyIGRlcyBpbmZvcm1hdGlvbnMgY29tbWUgbGUgdGl0cmUgZXQgbGUgcsOpc3Vtw6kuPHA+PHA+U2kgdm91cyBuZSBsJ2F2ZXogcGFzIGVuY29yZSBmYWl0LCBtZXJjaSBkZSBjb25zdWx0ZXIgbm9zIDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL3B1YmxpY2tub3dsZWRnZS9hYm91dC9zdWJtaXNzaW9ucyIgdGFyZ2V0PSJfYmxhbmsiPlJlY29tbWFuZGF0aW9ucyBwb3VyIGxhIHNvdW1pc3Npb248L2E+LiBMb3JzcXVlIHZvdXMgcmVtcGxpc3NleiBsZXMgZm9ybXVsYWlyZXMsIG1lcmNpIGRlIGZvdXJuaXIgYXV0YW50IGRlIGTDqXRhaWxzIHF1ZSBwb3NzaWJsZSBwb3VyIGFpZGVyIG5vcyDDqWRpdGV1ci50cmljZS5zIMOgIMOpdmFsdWVyIHZvdHJlIHRyYXZhaWwuIDwvcD48cD5VbmUgZm9pcyBxdWUgdm91cyBhdmV6IGNvbW1lbmPDqSwgdm91cyBwb3V2ZXogZW5yZWdpc3RyZXIgdm90cmUgc291bWlzc2lvbiBldCB5IHJldmVuaXIgcGx1cyB0YXJkLiBWb3VzIHBvdXJyZXogYWxvcnMgcsOpdmlzZXIgZXQgbW9kaWZpZXIgdG91dGVzIGxlcyBpbmZvcm1hdGlvbnMgdm91bHVlcyBhdmFudCBkZSBzb3VtZXR0cmUgbGUgdG91dC48L3A+Ijt9czoxMjoiY29udGFjdEVtYWlsIjtzOjIwOiJydmFjYUBtYWlsaW5hdG9yLmNvbSI7czoxMToiY29udGFjdE5hbWUiO3M6MTE6IlJhbWlybyBWYWNhIjtzOjE2OiJjb250cmlidXRvcnNIZWxwIjthOjI6e3M6MjoiZW4iO3M6NTA0OiI8cD5BZGQgZGV0YWlscyBmb3IgYWxsIG9mIHRoZSBjb250cmlidXRvcnMgdG8gdGhpcyBzdWJtaXNzaW9uLiBDb250cmlidXRvcnMgYWRkZWQgaGVyZSB3aWxsIGJlIHNlbnQgYW4gZW1haWwgY29uZmlybWF0aW9uIG9mIHRoZSBzdWJtaXNzaW9uLCBhcyB3ZWxsIGFzIGEgY29weSBvZiBhbGwgZWRpdG9yaWFsIGRlY2lzaW9ucyByZWNvcmRlZCBhZ2FpbnN0IHRoaXMgc3VibWlzc2lvbi48L3A+PHA+SWYgYSBjb250cmlidXRvciBjYW4gbm90IGJlIGNvbnRhY3RlZCBieSBlbWFpbCwgYmVjYXVzZSB0aGV5IG11c3QgcmVtYWluIGFub255bW91cyBvciBkbyBub3QgaGF2ZSBhbiBlbWFpbCBhY2NvdW50LCBwbGVhc2UgZG8gbm90IGVudGVyIGEgZmFrZSBlbWFpbCBhZGRyZXNzLiBZb3UgY2FuIGFkZCBpbmZvcm1hdGlvbiBhYm91dCB0aGlzIGNvbnRyaWJ1dG9yIGluIGEgbWVzc2FnZSB0byB0aGUgZWRpdG9yIGF0IGEgbGF0ZXIgc3RlcCBpbiB0aGUgc3VibWlzc2lvbiBwcm9jZXNzLjwvcD4iO3M6NToiZnJfQ0EiO3M6NjEzOiI8cD5Bam91dGVyIGRlcyBpbmZvcm1hdGlvbnMgcmVsYXRpdmVzIMOgIHRvdXMgbGVzIGNvbnRyaWJ1dGV1cnMudHJpY2VzIMOgIGNldHRlIHNvdW1pc3Npb24uIExlcyBjb250cmlidXRldXJzLnRyaWNlcyBham91dMOpLmUucyBpY2kgc2UgdmVycm9udCBlbnZveWVyIHVuIGNvdXJyaWVsIGRlIGNvbmZpcm1hdGlvbiBkZSBsYSBzb3VtaXNzaW9uIGFpbnNpIHF1J3VuZSBjb3BpZSBkZSB0b3V0ZXMgbGVzIGTDqWNpc2lvbnMgw6lkaXRvcmlhbGVzIGVucmVnaXN0csOpZXMgcG91ciBjZXR0ZSBzb3VtaXNzaW9uLjwvcD48cD5TaSB1bi5lIGNvbnRyaWJ1dGV1ci50cmljZSBuZSBwZXV0IMOqdHJlIGNvbnRhY3TDqS5lIHBhciBjb3VycmllbCBwYXJjZSBxdSdpbCBvdSBlbGxlIGRvaXQgZGVtZXVyZXIgYW5vbnltZSBvdSBuJ2EgcGFzIGRlIGNvbXB0ZSBkZSBtZXNzYWdlcmllLCB2ZXVpbGxleiBuZSBwYXMgZW50cmVyIGRlIGNvdXJyaWVsIGZpY3RpZi4gVm91cyBwb3V2ZXogYWpvdXRlciBkZXMgaW5mb3JtYXRpb25zIHN1ciBjZSBvdSBjZXR0ZSBjb250cmlidXRldXIudHJpY2Ugw6AgdW5lIMOpdGFwZSB1bHTDqXJpZXVyZSBkdSBwcm9jZXNzdXMgZGUgc291bWlzc2lvbi48L3A+Ijt9czo3OiJjb3VudHJ5IjtzOjI6IklTIjtzOjE3OiJkZWZhdWx0UmV2aWV3TW9kZSI7aToyO3M6MTE6ImRlc2NyaXB0aW9uIjthOjI6e3M6MjoiZW4iO3M6MTIzOiI8cD5UaGUgSm91cm5hbCBvZiBQdWJsaWMgS25vd2xlZGdlIGlzIGEgcGVlci1yZXZpZXdlZCBxdWFydGVybHkgcHVibGljYXRpb24gb24gdGhlIHN1YmplY3Qgb2YgcHVibGljIGFjY2VzcyB0byBzY2llbmNlLjwvcD4iO3M6NToiZnJfQ0EiO3M6MTQ2OiI8cD5MZSBKb3VybmFsIGRlIFB1YmxpYyBLbm93bGVkZ2UgZXN0IHVuZSBwdWJsaWNhdGlvbiB0cmltZXN0cmllbGxlIMOpdmFsdcOpZSBwYXIgbGVzIHBhaXJzIHN1ciBsZSB0aMOobWUgZGUgbCdhY2PDqHMgZHUgcHVibGljIMOgIGxhIHNjaWVuY2UuPC9wPiI7fXM6MTE6ImRldGFpbHNIZWxwIjthOjI6e3M6MjoiZW4iO3M6OTI6IjxwPlBsZWFzZSBwcm92aWRlIHRoZSBmb2xsb3dpbmcgZGV0YWlscyB0byBoZWxwIHVzIG1hbmFnZSB5b3VyIHN1Ym1pc3Npb24gaW4gb3VyIHN5c3RlbS48L3A+IjtzOjU6ImZyX0NBIjtzOjExNzoiPHA+VmV1aWxsZXogZm91cm5pciBsZXMgaW5mb3JtYXRpb25zIHN1aXZhbnRlcyBhZmluIGRlIG5vdXMgYWlkZXIgw6AgZ8OpcmVyIHZvdHJlIHNvdW1pc3Npb24gZGFucyBub3RyZSBzeXN0w6htZS48L3A+Ijt9czozMToiY29weVN1Ym1pc3Npb25BY2tQcmltYXJ5Q29udGFjdCI7YjowO3M6MjQ6ImNvcHlTdWJtaXNzaW9uQWNrQWRkcmVzcyI7czowOiIiO3M6MTQ6ImVtYWlsU2lnbmF0dXJlIjtzOjE0MToiPGJyPjxicj7igJQ8YnI+PHA+VGhpcyBpcyBhbiBhdXRvbWF0ZWQgbWVzc2FnZSBmcm9tIDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL3B1YmxpY2tub3dsZWRnZSI+Sm91cm5hbCBvZiBQdWJsaWMgS25vd2xlZGdlPC9hPi48L3A+IjtzOjEwOiJlbmFibGVEb2lzIjtiOjE7czoxMzoiZG9pU3VmZml4VHlwZSI7czo3OiJkZWZhdWx0IjtzOjE4OiJyZWdpc3RyYXRpb25BZ2VuY3kiO3M6MTQ6ImNyb3NzcmVmcGx1Z2luIjtzOjE4OiJkaXNhYmxlU3VibWlzc2lvbnMiO2I6MDtzOjE5OiJlZGl0b3JpYWxTdGF0c0VtYWlsIjtiOjE7czoxNzoiZm9yVGhlRWRpdG9yc0hlbHAiO2E6Mjp7czoyOiJlbiI7czoyNzg6IjxwPlBsZWFzZSBwcm92aWRlIHRoZSBmb2xsb3dpbmcgZGV0YWlscyBpbiBvcmRlciB0byBoZWxwIG91ciBlZGl0b3JpYWwgdGVhbSBtYW5hZ2UgeW91ciBzdWJtaXNzaW9uLjwvcD48cD5XaGVuIGVudGVyaW5nIG1ldGFkYXRhLCBwcm92aWRlIGVudHJpZXMgdGhhdCB5b3UgdGhpbmsgd291bGQgYmUgbW9zdCBoZWxwZnVsIHRvIHRoZSBwZXJzb24gbWFuYWdpbmcgeW91ciBzdWJtaXNzaW9uLiBUaGlzIGluZm9ybWF0aW9uIGNhbiBiZSBjaGFuZ2VkIGJlZm9yZSBwdWJsaWNhdGlvbi48L3A+IjtzOjU6ImZyX0NBIjtzOjMyOToiPHA+UydpbCB2b3VzIHBsYcOudCwgZm91cm5pc3NleiBsZXMgZMOpdGFpbHMgc3VpdmFudHMgYWZpbiBkJ2FpZGVyIGwnw6lxdWlwZSDDqWRpdG9yaWFsZSDDoCBnw6lyZXIgdm90cmUgc291bWlzc2lvbi48L3A+PHA+RGFucyB2b3MgbcOpdGFkb25uw6llcywgYXNzdXJleiB2b3VzIGRlIGZvdXJuaXIgZGVzIGluZm9ybWF0aW9ucyBxdWUgdm91cyBwZW5zZXogcG91dm9pciDDqnRyZSB1dGlsZSDDoCBsYSBwZXJzb25uZSBxdWkgZ8OpcmVyYSB2b3RyZSBzb3VtaXNzaW9uLiBDZXR0ZSBpbmZvcm1hdGlvbiBwZXV0IMOqdHJlIGNoYW5nw6llIGF2YW50IHB1YmxpY2F0aW9uLjwvcD4iO31zOjEyOiJpdGVtc1BlclBhZ2UiO2k6MjU7czo4OiJrZXl3b3JkcyI7czo3OiJyZXF1ZXN0IjtzOjIwOiJsaWJyYXJpYW5JbmZvcm1hdGlvbiI7YToyOntzOjI6ImVuIjtzOjM2MToiV2UgZW5jb3VyYWdlIHJlc2VhcmNoIGxpYnJhcmlhbnMgdG8gbGlzdCB0aGlzIGpvdXJuYWwgYW1vbmcgdGhlaXIgbGlicmFyeSdzIGVsZWN0cm9uaWMgam91cm5hbCBob2xkaW5ncy4gQXMgd2VsbCwgaXQgbWF5IGJlIHdvcnRoIG5vdGluZyB0aGF0IHRoaXMgam91cm5hbCdzIG9wZW4gc291cmNlIHB1Ymxpc2hpbmcgc3lzdGVtIGlzIHN1aXRhYmxlIGZvciBsaWJyYXJpZXMgdG8gaG9zdCBmb3IgdGhlaXIgZmFjdWx0eSBtZW1iZXJzIHRvIHVzZSB3aXRoIGpvdXJuYWxzIHRoZXkgYXJlIGludm9sdmVkIGluIGVkaXRpbmcgKHNlZSA8YSBocmVmPSJodHRwczovL3BrcC5zZnUuY2Evb2pzIj5PcGVuIEpvdXJuYWwgU3lzdGVtczwvYT4pLiI7czo1OiJmcl9DQSI7czo0MzQ6Ik5vdXMgaW5jaXRvbnMgbGVzIGJpYmxpb3Row6ljYWlyZXMgw6AgbGlzdGVyIGNldHRlIHJldnVlIGRhbnMgbGV1ciBmb25kcyBkZSByZXZ1ZXMgbnVtw6lyaXF1ZXMuIEF1c3NpLCBpbCBwZXV0IMOqdHJlIHBlcnRpbmVudCBkZSBtZW50aW9ubmVyIHF1ZSBjZSBzeXN0w6htZSBkZSBwdWJsaWNhdGlvbiBlbiBsaWJyZSBhY2PDqHMgZXN0IGNvbsOndSBwb3VyIMOqdHJlIGjDqWJlcmfDqSBwYXIgbGVzIGJpYmxpb3Row6hxdWVzIGRlIHJlY2hlcmNoZSBwb3VyIHF1ZSBsZXMgbWVtYnJlcyBkZSBsZXVycyBmYWN1bHTDqXMgbCd1dGlsaXNlbnQgYXZlYyBsZXMgcmV2dWVzIGRhbnMgbGVzcXVlbGxlcyBlbGxlcyBvdSBpbHMgc29udCBpbXBsaXF1w6lzICh2b2lyIDxhIGhyZWY9Imh0dHBzOi8vcGtwLnNmdS5jYS9vanMiPk9wZW4gSm91cm5hbCBTeXN0ZW1zPC9hPikuIjt9czo0OiJuYW1lIjthOjI6e3M6MjoiZW4iO3M6MTQ6IlRlc3QgSm91cm5hbCAxIjtzOjU6ImZyX0NBIjtzOjM2OiJKb3VybmFsIGRlIGxhIGNvbm5haXNzYW5jZSBkdSBwdWJsaWMiO31zOjE2OiJub3RpZnlBbGxBdXRob3JzIjtiOjE7czoxMjoibnVtUGFnZUxpbmtzIjtpOjEwO3M6MTk6Im51bVdlZWtzUGVyUmVzcG9uc2UiO2k6NDtzOjE3OiJudW1XZWVrc1BlclJldmlldyI7aTo0O3M6MjU6Im51bVJldmlld2Vyc1BlclN1Ym1pc3Npb24iO2k6MDtzOjE2OiJvcGVuQWNjZXNzUG9saWN5IjthOjI6e3M6MjoiZW4iO3M6MTc2OiJUaGlzIGpvdXJuYWwgcHJvdmlkZXMgaW1tZWRpYXRlIG9wZW4gYWNjZXNzIHRvIGl0cyBjb250ZW50IG9uIHRoZSBwcmluY2lwbGUgdGhhdCBtYWtpbmcgcmVzZWFyY2ggZnJlZWx5IGF2YWlsYWJsZSB0byB0aGUgcHVibGljIHN1cHBvcnRzIGEgZ3JlYXRlciBnbG9iYWwgZXhjaGFuZ2Ugb2Yga25vd2xlZGdlLiI7czo1OiJmcl9DQSI7czoyMTc6IkNldHRlIHJldnVlIGZvdXJuaXQgbGUgbGlicmUgYWNjw6hzIGltbcOpZGlhdCDDoCBzb24gY29udGVudSBzZSBiYXNhbnQgc3VyIGxlIHByaW5jaXBlIHF1ZSByZW5kcmUgbGEgcmVjaGVyY2hlIGRpc3BvbmlibGUgYXUgcHVibGljIGdyYXR1aXRlbWVudCBmYWNpbGl0ZSB1biBwbHVzIGdyYW5kIMOpY2hhbmdlIGR1IHNhdm9pciwgw6AgbCfDqWNoZWxsZSBkZSBsYSBwbGFuw6h0ZS4iO31zOjk6Im9yY2lkQ2l0eSI7czowOiIiO3M6MTM6Im9yY2lkQ2xpZW50SWQiO3M6MDoiIjtzOjE3OiJvcmNpZENsaWVudFNlY3JldCI7czowOiIiO3M6MTI6Im9yY2lkRW5hYmxlZCI7YjowO3M6MTM6Im9yY2lkTG9nTGV2ZWwiO3M6NToiRVJST1IiO3M6MzU6Im9yY2lkU2VuZE1haWxUb0F1dGhvcnNPblB1YmxpY2F0aW9uIjtiOjA7czoxNjoicHJpdmFjeVN0YXRlbWVudCI7YToyOntzOjI6ImVuIjtzOjIwNjoiPHA+VGhlIG5hbWVzIGFuZCBlbWFpbCBhZGRyZXNzZXMgZW50ZXJlZCBpbiB0aGlzIGpvdXJuYWwgc2l0ZSB3aWxsIGJlIHVzZWQgZXhjbHVzaXZlbHkgZm9yIHRoZSBzdGF0ZWQgcHVycG9zZXMgb2YgdGhpcyBqb3VybmFsIGFuZCB3aWxsIG5vdCBiZSBtYWRlIGF2YWlsYWJsZSBmb3IgYW55IG90aGVyIHB1cnBvc2Ugb3IgdG8gYW55IG90aGVyIHBhcnR5LjwvcD4iO3M6NToiZnJfQ0EiO3M6MTkzOiI8cD5MZXMgbm9tcyBldCBjb3VycmllbHMgc2Fpc2lzIGRhbnMgbGUgc2l0ZSBkZSBjZXR0ZSByZXZ1ZSBzZXJvbnQgdXRpbGlzw6lzIGV4Y2x1c2l2ZW1lbnQgYXV4IGZpbnMgaW5kaXF1w6llcyBwYXIgY2V0dGUgcmV2dWUgZXQgbmUgc2Vydmlyb250IMOgIGF1Y3VuZSBhdXRyZSBmaW4sIG5pIMOgIHRvdXRlIGF1dHJlIHBhcnRpZS48L3A+Ijt9czoxNzoicmVhZGVySW5mb3JtYXRpb24iO2E6Mjp7czoyOiJlbiI7czo2NTQ6IldlIGVuY291cmFnZSByZWFkZXJzIHRvIHNpZ24gdXAgZm9yIHRoZSBwdWJsaXNoaW5nIG5vdGlmaWNhdGlvbiBzZXJ2aWNlIGZvciB0aGlzIGpvdXJuYWwuIFVzZSB0aGUgPGEgaHJlZj0iaHR0cDovL2xvY2FsaG9zdC9pbmRleC5waHAvcHVibGlja25vd2xlZGdlL3VzZXIvcmVnaXN0ZXIiPlJlZ2lzdGVyPC9hPiBsaW5rIGF0IHRoZSB0b3Agb2YgdGhlIGhvbWUgcGFnZSBmb3IgdGhlIGpvdXJuYWwuIFRoaXMgcmVnaXN0cmF0aW9uIHdpbGwgcmVzdWx0IGluIHRoZSByZWFkZXIgcmVjZWl2aW5nIHRoZSBUYWJsZSBvZiBDb250ZW50cyBieSBlbWFpbCBmb3IgZWFjaCBuZXcgaXNzdWUgb2YgdGhlIGpvdXJuYWwuIFRoaXMgbGlzdCBhbHNvIGFsbG93cyB0aGUgam91cm5hbCB0byBjbGFpbSBhIGNlcnRhaW4gbGV2ZWwgb2Ygc3VwcG9ydCBvciByZWFkZXJzaGlwLiBTZWUgdGhlIGpvdXJuYWwncyA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvYWJvdXQvc3VibWlzc2lvbnMjcHJpdmFjeVN0YXRlbWVudCI+UHJpdmFjeSBTdGF0ZW1lbnQ8L2E+LCB3aGljaCBhc3N1cmVzIHJlYWRlcnMgdGhhdCB0aGVpciBuYW1lIGFuZCBlbWFpbCBhZGRyZXNzIHdpbGwgbm90IGJlIHVzZWQgZm9yIG90aGVyIHB1cnBvc2VzLiI7czo1OiJmcl9DQSI7czo3MTY6Ik5vdXMgaW52aXRvbnMgbGVzIGxlY3RldXJzLXRyaWNlcyDDoCBzJ2luc2NyaXJlIHBvdXIgcmVjZXZvaXIgbGVzIGF2aXMgZGUgcHVibGljYXRpb24gZGUgY2V0dGUgcmV2dWUuIFV0aWxpc2VyIGxlIGxpZW4gPGEgaHJlZj0iaHR0cDovL2xvY2FsaG9zdC9pbmRleC5waHAvcHVibGlja25vd2xlZGdlL3VzZXIvcmVnaXN0ZXIiPlMnaW5zY3JpcmU8L2E+IGVuIGhhdXQgZGUgbGEgcGFnZSBkJ2FjY3VlaWwgZGUgbGEgcmV2dWUuIENldHRlIGluc2NyaXB0aW9uIHBlcm1ldHRyYSBhdSzDoCBsYSBsZWN0ZXVyLXRyaWNlIGRlIHJlY2V2b2lyIHBhciBjb3VycmllbCBsZSBzb21tYWlyZSBkZSBjaGFxdWUgbm91dmVhdSBudW3DqXJvIGRlIGxhIHJldnVlLiBDZXR0ZSBsaXN0ZSBwZXJtZXQgYXVzc2kgw6AgbGEgcmV2dWUgZGUgcmV2ZW5kaXF1ZXIgdW4gY2VydGFpbiBuaXZlYXUgZGUgc291dGllbiBvdSBkZSBsZWN0b3JhdC4gVm9pciBsYSA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvYWJvdXQvc3VibWlzc2lvbnMjcHJpdmFjeVN0YXRlbWVudCI+RMOpY2xhcmF0aW9uIGRlIGNvbmZpZGVudGlhbGl0w6k8L2E+IGRlIGxhIHJldnVlIHF1aSBjZXJ0aWZpZSBhdXggbGVjdGV1cnMtdHJpY2VzIHF1ZSBsZXVyIG5vbSBldCBsZXVyIGNvdXJyaWVsIG5lIHNlcm9udCBwYXMgdXRpbGlzw6lzIMOgIGQnYXV0cmVzIGZpbnMuIjt9czoxMDoicmV2aWV3SGVscCI7YToyOntzOjI6ImVuIjtzOjM2ODoiPHA+UmV2aWV3IHRoZSBpbmZvcm1hdGlvbiB5b3UgaGF2ZSBlbnRlcmVkIGJlZm9yZSB5b3UgY29tcGxldGUgeW91ciBzdWJtaXNzaW9uLiBZb3UgY2FuIGNoYW5nZSBhbnkgb2YgdGhlIGRldGFpbHMgZGlzcGxheWVkIGhlcmUgYnkgY2xpY2tpbmcgdGhlIGVkaXQgYnV0dG9uIGF0IHRoZSB0b3Agb2YgZWFjaCBzZWN0aW9uLjwvcD48cD5PbmNlIHlvdSBjb21wbGV0ZSB5b3VyIHN1Ym1pc3Npb24sIGEgbWVtYmVyIG9mIG91ciBlZGl0b3JpYWwgdGVhbSB3aWxsIGJlIGFzc2lnbmVkIHRvIHJldmlldyBpdC4gUGxlYXNlIGVuc3VyZSB0aGUgZGV0YWlscyB5b3UgaGF2ZSBlbnRlcmVkIGhlcmUgYXJlIGFzIGFjY3VyYXRlIGFzIHBvc3NpYmxlLjwvcD4iO3M6NToiZnJfQ0EiO3M6NDAyOiI8cD5Sw6l2aXNleiBsJ2luZm9ybWF0aW9uIHF1ZSB2b3VzIGF2ZXogZm91cm5pIGF2YW50IGRlIGZpbmFsaXNlciB2b3RyZSBzb3VtaXNzaW9uLiBWb3VzIHBvdXZleiBtb2RpZmllciBjaGFxdWUgZMOpdGFpbHMgYWZmaWNow6lzIGVuIGNsaXF1YW50IHN1ciBsZSBib3V0b24gZCfDqWRpdGlvbiBlbiBoYXV0IGRlIGNoYXF1ZSBzZWN0aW9uLjwvcD48cD5VbmUgZm9pcyB2b3RyZSBzb3VtaXNzaW9uIHRyYW5zbWlzZSwgdW4gbWVtYnJlIGRlIGwnw6lxdWlwZSDDqWRpdG9yaWFsZSBsdWkgc2VyYSBhc3NpZ27DqSBhZmluIGRlIGwnw6l2YWx1ZXIuIFMnaWwgdm91cyBwbGHDrnQsIGFzc3VyZXogdm91cyBxdWUgbGVzIGTDqXRhaWxzIGZvdXJuaXMgc29udCBsZSBwbHVzIGV4YWN0ZXMgcG9zc2libGVzLjwvcD4iO31zOjI1OiJzdWJtaXNzaW9uQWNrbm93bGVkZ2VtZW50IjtzOjEwOiJhbGxBdXRob3JzIjtzOjE5OiJzdWJtaXNzaW9uQ2hlY2tsaXN0IjthOjI6e3M6MjoiZW4iO3M6NTkxOiI8cD5BbGwgc3VibWlzc2lvbnMgbXVzdCBtZWV0IHRoZSBmb2xsb3dpbmcgcmVxdWlyZW1lbnRzLjwvcD48dWw+PGxpPlRoaXMgc3VibWlzc2lvbiBtZWV0cyB0aGUgcmVxdWlyZW1lbnRzIG91dGxpbmVkIGluIHRoZSA8YSBocmVmPSJodHRwOi8vbG9jYWxob3N0L2luZGV4LnBocC9wdWJsaWNrbm93bGVkZ2UvYWJvdXQvc3VibWlzc2lvbnMiPkF1dGhvciBHdWlkZWxpbmVzPC9hPi48L2xpPjxsaT5UaGlzIHN1Ym1pc3Npb24gaGFzIG5vdCBiZWVuIHByZXZpb3VzbHkgcHVibGlzaGVkLCBub3IgaXMgaXQgYmVmb3JlIGFub3RoZXIgam91cm5hbCBmb3IgY29uc2lkZXJhdGlvbi48L2xpPjxsaT5BbGwgcmVmZXJlbmNlcyBoYXZlIGJlZW4gY2hlY2tlZCBmb3IgYWNjdXJhY3kgYW5kIGNvbXBsZXRlbmVzcy48L2xpPjxsaT5BbGwgdGFibGVzIGFuZCBmaWd1cmVzIGhhdmUgYmVlbiBudW1iZXJlZCBhbmQgbGFiZWxlZC48L2xpPjxsaT5QZXJtaXNzaW9uIGhhcyBiZWVuIG9idGFpbmVkIHRvIHB1Ymxpc2ggYWxsIHBob3RvcywgZGF0YXNldHMgYW5kIG90aGVyIG1hdGVyaWFsIHByb3ZpZGVkIHdpdGggdGhpcyBzdWJtaXNzaW9uLjwvbGk+PC91bD4iO3M6NToiZnJfQ0EiO3M6NjQzOiI8cD5Ub3V0ZXMgbGVzIHNvdW1pc3Npb25zIGRvaXZlbnQgcsOpcG9uZHJlIGF1eCBleGlnZW5jZXMgc3VpdmFudGVzIDogPC9wPjx1bD48bGk+Q2V0dGUgc291bWlzc2lvbiByw6lwb25kIGF1eCBleGlnZW5jZXMgZMOpZmluaWVzIGRhbnMgbGVzIDxhIGhyZWY9Imh0dHA6Ly9sb2NhbGhvc3QvaW5kZXgucGhwL3B1YmxpY2tub3dsZWRnZS9hYm91dC9zdWJtaXNzaW9ucyI+ZGlyZWN0aXZlcyBhdXggYXV0ZXVyLmUuczwvYT4uPC9saT48bGk+Q2V0dGUgc291bWlzc2lvbiBuJ2Egbmkgw6l0w6kgcHVibGnDqWUgcHLDqWPDqWRlbW1lbnQsIG5pIMOpdMOpIHNvdW1pc2Ugw6AgdW5lIGF1dHJlIHJldnVlLjwvbGk+PGxpPlRvdXRlcyBsZXMgcsOpZsOpcmVuY2VzIG9udCDDqXTDqSB2w6lyaWZpw6llcyBldCBzb250IGV4YWN0ZXMuPC9saT48bGk+VG91cyBsZXMgdGFibGVhdXggZXQgZmlndXJlcyBzb250IG51bcOpcm90w6lzIGV0IGTDqWZpbmlzLjwvbGk+PGxpPkwnYXV0b3Jpc2F0aW9uIGRlIHB1YmxpZXIgdG91dGVzIGxlcyBwaG90b3MsIHRvdXMgbGVzIGVuc2VtYmxlcyBkZSBkb25uw6llcyBldCB0b3V0IGF1dHJlIG1hdMOpcmllbCBmb3VybmkgYXZlYyBjZXR0ZSBzb3VtaXNzaW9uIGEgw6l0w6kgb2J0ZW51ZS48L2xpPjwvdWw+Ijt9czoyMDoic3VibWl0V2l0aENhdGVnb3JpZXMiO2I6MDtzOjMxOiJzdXBwb3J0ZWRBZGRlZFN1Ym1pc3Npb25Mb2NhbGVzIjthOjI6e2k6MDtzOjI6ImVuIjtpOjE7czo1OiJmcl9DQSI7fXM6MzI6InN1cHBvcnRlZERlZmF1bHRTdWJtaXNzaW9uTG9jYWxlIjtzOjI6ImVuIjtzOjIwOiJzdXBwb3J0ZWRGb3JtTG9jYWxlcyI7YToyOntpOjA7czoyOiJlbiI7aToxO3M6NToiZnJfQ0EiO31zOjE2OiJzdXBwb3J0ZWRMb2NhbGVzIjthOjI6e2k6MDtzOjI6ImVuIjtpOjE7czo1OiJmcl9DQSI7fXM6MjY6InN1cHBvcnRlZFN1Ym1pc3Npb25Mb2NhbGVzIjthOjI6e2k6MDtzOjI6ImVuIjtpOjE7czo1OiJmcl9DQSI7fXM6MzQ6InN1cHBvcnRlZFN1Ym1pc3Npb25NZXRhZGF0YUxvY2FsZXMiO2E6Mjp7aTowO3M6MjoiZW4iO2k6MTtzOjU6ImZyX0NBIjt9czoxNToidGhlbWVQbHVnaW5QYXRoIjtzOjc6ImRlZmF1bHQiO3M6MTU6InVwbG9hZEZpbGVzSGVscCI7YToyOntzOjI6ImVuIjtzOjI0OToiPHA+UHJvdmlkZSBhbnkgZmlsZXMgb3VyIGVkaXRvcmlhbCB0ZWFtIG1heSBuZWVkIHRvIGV2YWx1YXRlIHlvdXIgc3VibWlzc2lvbi4gSW4gYWRkaXRpb24gdG8gdGhlIG1haW4gd29yaywgeW91IG1heSB3aXNoIHRvIHN1Ym1pdCBkYXRhIHNldHMsIGNvbmZsaWN0IG9mIGludGVyZXN0IHN0YXRlbWVudHMsIG9yIG90aGVyIHN1cHBsZW1lbnRhcnkgZmlsZXMgaWYgdGhlc2Ugd2lsbCBiZSBoZWxwZnVsIGZvciBvdXIgZWRpdG9ycy48L3A+IjtzOjU6ImZyX0NBIjtzOjMxNzoiPHA+IEZvdXJuaXIgdG91cyBsZXMgZmljaGllcnMgZG9udCBub3RyZSDDqXF1aXBlIMOpZGl0b3JpYWxlIHBvdXJyYWl0IGF2b2lyIGJlc29pbiBwb3VyIMOpdmFsdWVyIHZvdHJlIHNvdW1pc3Npb24uIEVuIHBsdXMgZHUgZmljaGllciBwcmluY2lwYWwsIHZvdXMgcG91dmV6IHNvdW1ldHRyZSBkZXMgZW5zZW1ibGVzIGRlIGRvbm7DqWVzLCB1bmUgZMOpY2xhcmF0aW9uIHJlbGF0aXZlIGF1IGNvbmZsaXQgZCdpbnTDqXLDqnQgb3UgdG91dCBhdXRyZSBmaWNoaWVyIHBvdGVudGllbGxlbWVudCB1dGlsZSBwb3VyIG5vcyDDqWRpdGV1ci50cmljZS5zLjwvcD4iO31zOjE5OiJlbmFibGVHZW9Vc2FnZVN0YXRzIjtzOjg6ImRpc2FibGVkIjtzOjI3OiJlbmFibGVJbnN0aXR1dGlvblVzYWdlU3RhdHMiO2I6MDtzOjE2OiJpc1N1c2hpQXBpUHVibGljIjtiOjE7czoxNDoiY2xvY2tzc0xpY2Vuc2UiO2E6Mjp7czoyOiJlbiI7czoyNzE6IlRoaXMgam91cm5hbCB1dGlsaXplcyB0aGUgQ0xPQ0tTUyBzeXN0ZW0gdG8gY3JlYXRlIGEgZGlzdHJpYnV0ZWQgYXJjaGl2aW5nIHN5c3RlbSBhbW9uZyBwYXJ0aWNpcGF0aW5nIGxpYnJhcmllcyBhbmQgcGVybWl0cyB0aG9zZSBsaWJyYXJpZXMgdG8gY3JlYXRlIHBlcm1hbmVudCBhcmNoaXZlcyBvZiB0aGUgam91cm5hbCBmb3IgcHVycG9zZXMgb2YgcHJlc2VydmF0aW9uIGFuZCByZXN0b3JhdGlvbi4gPGEgaHJlZj0iaHR0cHM6Ly9jbG9ja3NzLm9yZyI+TW9yZS4uLjwvYT4iO3M6NToiZnJfQ0EiO3M6MzE1OiJDZXR0ZSByZXZ1ZSB1dGlsaXNlIGxlIHN5c3TDqG1lIENMT0NLU1MgcG91ciBjcsOpZXIgdW4gc3lzdMOobWUgZCdhcmNoaXZhZ2UgZGlzdHJpYnXDqSBwYXJtaSBsZXMgYmlibGlvdGjDqHF1ZXMgcGFydGljaXBhbnRlcyBldCBwZXJtZXQgw6AgY2VzIGJpYmxpb3Row6hxdWVzIGRlIGNyw6llciBkZXMgYXJjaGl2ZXMgcGVybWFuZW50ZXMgZGUgbGEgcmV2dWUgw6AgZGVzIGZpbnMgZGUgY29uc2VydmF0aW9uIGV0IGRlIHJlY29uc3RpdHV0aW9uLiA8YSBocmVmPSJodHRwczovL2Nsb2Nrc3Mub3JnIj5FbiBhcHByZW5kcmUgZGF2YW50YWdlLi4uIDwvYT4iO31zOjE4OiJjb3B5cmlnaHRZZWFyQmFzaXMiO3M6NToiaXNzdWUiO3M6MTU6ImVuYWJsZWREb2lUeXBlcyI7YToyOntpOjA7czoxMToicHVibGljYXRpb24iO2k6MTtzOjU6Imlzc3VlIjt9czoxNToiZG9pQ3JlYXRpb25UaW1lIjtzOjIwOiJjb3B5RWRpdENyZWF0aW9uVGltZSI7czo5OiJlbmFibGVPYWkiO2I6MTtzOjEzOiJsb2Nrc3NMaWNlbnNlIjthOjI6e3M6MjoiZW4iO3M6MjczOiJUaGlzIGpvdXJuYWwgdXRpbGl6ZXMgdGhlIExPQ0tTUyBzeXN0ZW0gdG8gY3JlYXRlIGEgZGlzdHJpYnV0ZWQgYXJjaGl2aW5nIHN5c3RlbSBhbW9uZyBwYXJ0aWNpcGF0aW5nIGxpYnJhcmllcyBhbmQgcGVybWl0cyB0aG9zZSBsaWJyYXJpZXMgdG8gY3JlYXRlIHBlcm1hbmVudCBhcmNoaXZlcyBvZiB0aGUgam91cm5hbCBmb3IgcHVycG9zZXMgb2YgcHJlc2VydmF0aW9uIGFuZCByZXN0b3JhdGlvbi4gPGEgaHJlZj0iaHR0cHM6Ly93d3cubG9ja3NzLm9yZyI+TW9yZS4uLjwvYT4iO3M6NToiZnJfQ0EiO3M6MzE0OiJDZXR0ZSByZXZ1ZSB1dGlsaXNlIGxlIHN5c3TDqG1lIExPQ0tTUyBwb3VyIGNyw6llciB1biBzeXN0w6htZSBkZSBkaXN0cmlidXRpb24gZGVzIGFyY2hpdmVzIHBhcm1pIGxlcyBiaWJsaW90aMOocXVlcyBwYXJ0aWNpcGFudGVzIGV0IGFmaW4gZGUgcGVybWV0dHJlIMOgIGNlcyBiaWJsaW90aMOocXVlcyBkZSBjcsOpZXIgZGVzIGFyY2hpdmVzIHBlcm1hbmVudGVzIHBvdXIgZmlucyBkZSBwcsOpc2VydmF0aW9uIGV0IGRlIHJlc3RhdXJhdGlvbi4gPGEgaHJlZj0iaHR0cHM6Ly9sb2Nrc3Mub3JnIj5FbiBhcHByZW5kcmUgZGF2YW50YWdlLi4uPC9hPiI7fXM6MTM6Im1lbWJlcnNoaXBGZWUiO2Q6MDtzOjE0OiJwdWJsaWNhdGlvbkZlZSI7ZDowO3M6MTg6InB1cmNoYXNlQXJ0aWNsZUZlZSI7ZDowO3M6MTM6ImRvaVZlcnNpb25pbmciO2I6MDtzOjEzOiJjdXN0b21IZWFkZXJzIjthOjE6e3M6MjoiZW4iO3M6NDE6IjxtZXRhIG5hbWU9InBrcCIgY29udGVudD0iVGVzdCBtZXRhdGFnLiI+Ijt9czoxNzoic2VhcmNoRGVzY3JpcHRpb24iO2E6MTp7czoyOiJlbiI7czoxMTY6IlRoZSBKb3VybmFsIG9mIFB1YmxpYyBLbm93bGVkZ2UgaXMgYSBwZWVyLXJldmlld2VkIHF1YXJ0ZXJseSBwdWJsaWNhdGlvbiBvbiB0aGUgc3ViamVjdCBvZiBwdWJsaWMgYWNjZXNzIHRvIHNjaWVuY2UuIjt9czoxMjoiYWJicmV2aWF0aW9uIjthOjE6e3M6MjoiZW4iO3M6MjU6InB1YmxpY2tub3dsZWRnZUogUHViIEtub3ciO31zOjEwOiJvbmxpbmVJc3NuIjtzOjk6IjE0MzgtNTYyNyI7czoyMDoicHVibGlzaGVySW5zdGl0dXRpb24iO3M6MjQ6IlB1YmxpYyBLbm93bGVkZ2UgUHJvamVjdCI7czoxNDoibWFpbGluZ0FkZHJlc3MiO3M6NDk6IjEyMyA0NTZ0aCBTdHJlZXQKQnVybmFieSwgQnJpdGlzaCBDb2x1bWJpYQpDYW5hZGEiO3M6MTI6InN1cHBvcnRFbWFpbCI7czoyMDoicnZhY2FAbWFpbGluYXRvci5jb20iO3M6MTE6InN1cHBvcnROYW1lIjtzOjExOiJSYW1pcm8gVmFjYSI7czo5OiJkb2lQcmVmaXgiO3M6NzoiMTAuOTg3NiI7czoxOToiYXV0b21hdGljRG9pRGVwb3NpdCI7YjowO31zOjIwOiJfaGFzTG9hZGFibGVBZGFwdGVycyI7YjowO3M6Mjc6Il9tZXRhZGF0YUV4dHJhY3Rpb25BZGFwdGVycyI7YTowOnt9czoyNToiX2V4dHJhY3Rpb25BZGFwdGVyc0xvYWRlZCI7YjowO3M6MjY6Il9tZXRhZGF0YUluamVjdGlvbkFkYXB0ZXJzIjthOjA6e31zOjI0OiJfaW5qZWN0aW9uQWRhcHRlcnNMb2FkZWQiO2I6MDtzOjEzOiJfbG9jYWxlc1RhYmxlIjthOjk6e3M6MTE6ImJlQGN5cmlsbGljIjtzOjI6ImJlIjtzOjI6ImJzIjtzOjc6ImJzX0xhdG4iO3M6NToiZnJfRlIiO3M6MjoiZnIiO3M6MjoibmIiO3M6NToibmJfTk8iO3M6MTE6InNyQGN5cmlsbGljIjtzOjc6InNyX0N5cmwiO3M6ODoic3JAbGF0aW4iO3M6Nzoic3JfTGF0biI7czoxMToidXpAY3lyaWxsaWMiO3M6MjoidXoiO3M6ODoidXpAbGF0aW4iO3M6NzoidXpfTGF0biI7czo1OiJ6aF9DTiI7czo3OiJ6aF9IYW5zIjt9fXM6NDM6IgBQS1Bcam9ic1xvcmNpZFxTZW5kQXV0aG9yTWFpbAB1cGRhdGVBdXRob3IiO2I6MDt9";

    public function getMockedDAOs(): array
    {
         return [...parent::getMockedDAOs(), 'SiteDAO'];
    }

    /**
     * Test job is a proper instance
     */
    public function testUnserializeGetProperJobInstance(): void
    {
        $this->assertInstanceOf(
            SendAuthorMail::class,
            unserialize(base64_decode($this->serializedJobData)),
        );
    }


    public function testRunSerializedJob(): void
    {
        $this->mockRequest();
        $this->mockMail();
        Mail::shouldReceive('send')
            ->withAnyArgs();

        /** @var SendAuthorMail $sendAuthorMailJob */
        $sendAuthorMailJob = unserialize(base64_decode($this->serializedJobData));

        // Publication mocks
        $publicationMock = Mockery::mock(Publication::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('submissionId')
            ->andReturn(1)
            ->getMock();

        $publicationDaoMock = Mockery::mock(\APP\publication\DAO::class, [
            new SubmissionKeywordDAO(),
            new SubmissionSubjectDAO(),
            new SubmissionDisciplineDAO(),
            new SubmissionAgencyDAO(),
            new CitationDAO(),
            new PKPSchemaService(),
        ])
            ->makePartial()
            ->shouldReceive([
                'fromRow' => $publicationMock,
            ])
            ->withAnyArgs()
            ->getMock();

        $publicationRepoMock = Mockery::mock(app(PublicationRepository::class))
            ->makePartial()
            ->shouldReceive('get')
            ->withAnyArgs()
            ->andReturn($publicationMock)
            ->set('dao', $publicationDaoMock)
            ->getMock();

        app()->instance(PublicationRepository::class, $publicationRepoMock);

        // Submission mocks
        $submissionMock = Mockery::mock(Submission::class)
            ->makePartial()
            ->shouldReceive([
                'getCurrentPublication' => $publicationMock,
            ])
            ->getMock();

        $submissionDaoMock = Mockery::mock(\APP\submission\DAO::class, [
            new PKPSchemaService()
        ])
            ->makePartial()
            ->shouldReceive([
                'fromRow' => $submissionMock,
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

        $siteMock = Mockery::mock(Site::class)
            ->makePartial()
            ->shouldReceive('getData')
            ->with('orcidEnabled')
            ->andReturn(false)
            ->getMOck();

        $siteDaoMock = Mockery::mock(SiteDAO::class)
            ->makePartial()
            ->shouldReceive([
                'fromRow' => $siteMock,
            ])
           ->withAnyArgs()
           ->getMock();

        DAORegistry::registerDAO('SiteDAO', $siteDaoMock);

        $this->assertNull($sendAuthorMailJob->handle());
    }
}
