{**
 * templates/frontend/pages/userConfirmActivation.tpl
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief A landing page get user confirmation to activate the user account
 *}
 {include file="frontend/components/header.tpl"}

 <div class="page">
     {include file="frontend/components/breadcrumbs.tpl" currentTitleKey=$pageTitle}
 
     <div class="page user_confirm_activation">
 
         <p>{translate key="user.login.activate.description"}</p>
 
         <a href="{$activationUrl}" class="button pkp_button">
             {translate key="user.login.activate"}
         </a>
     </div>
 </div>
 
 {include file="frontend/components/footer.tpl"}
 