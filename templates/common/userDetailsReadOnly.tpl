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

<div id="userDetailsReadOnly" style="background-color: #EBEDEE; padding: 6px; margin-top: 5px; margin-bottom: 5px;">
    <div class="section">				
        <div class="inline pkp_helpers_third">
            <span style="font-wight: bold; color: #777777;">{translate key="user.givenName"}</span><br />
            <span>{$givenName|array_pop}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span style="font-wight: bold; color: #777777;">{translate key="user.familyName"}</span><br />
            <span>{$familyName|array_pop}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span style="font-wight: bold; color: #777777;">{translate key="user.preferredPublicName"}</span><br />
            <span>{$preferredPublicName|array_pop}</span>
        </div>
    </div>
    <div class="section">				
        <div class="inline pkp_helpers_third">
            <span style="font-wight: bold; color: #777777;">{translate key="user.email"}</span><br />
            <span>{$email}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span style="font-wight: bold; color: #777777;">{translate key="common.country"}</span><br />
            <span>{$countries[$country]}</span>
        </div>
        <div class="inline pkp_helpers_third">
            <span style="font-wight: bold; color: #777777;">{translate key="user.phone"}</span><br />
            <span>{$phone}</span>
        </div>
    </div>
</div>