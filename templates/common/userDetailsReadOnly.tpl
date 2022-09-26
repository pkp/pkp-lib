{**
 * common/userDetailsReadOnly.tpl
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * User details view only
 *}
<h4>{translate key="grid.user.userDetails"}</h4>

<div id="userDetailsReadOnly">
    <div class="section">				
        <div class="inline pkp_helpers_third">
            <span class="pkp_helpers_form_input_title_text">{translate key="user.givenName"}</span><br />
            <span>{$user->getLocalizedData('givenName', $site->getPrimaryLocale())|escape}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span class="pkp_helpers_form_input_title_text">{translate key="user.familyName"}</span><br />
            <span>{$user->getLocalizedData('familyName', $site->getPrimaryLocale())|escape}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span class="pkp_helpers_form_input_title_text">{translate key="user.preferredPublicName"}</span><br />
            <span>{$user->getLocalizedData('preferredPublicName', $site->getPrimaryLocale())|escape}</span>
        </div>
    </div>
    <div class="section">				
        <div class="inline pkp_helpers_third">
            <span class="pkp_helpers_form_input_title_text">{translate key="user.email"}</span><br />
            <span>{$email|escape}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span class="pkp_helpers_form_input_title_text">{translate key="common.country"}</span><br />
            <span>{$countries[$user->getCountry()]|escape}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span class="pkp_helpers_form_input_title_text">{translate key="user.phone"}</span><br />
            <span>{$user->getPhone()|escape}</span>
        </div>
    </div>
</div>