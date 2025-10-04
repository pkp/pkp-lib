{extends file="layouts/backend.tpl"}
{block name="page"}
    <div class="page page_invitation_decline">
        <h1>
            {translate key="invitation.decline.confirm.title"}
        </h1>
        <p>
            {translate key="invitation.decline.confirm.description"}
        </p>
        <pkp-button
            element="a"
            href="{$declineUrl}"
        >
            {translate key="invitation.decline.confirm"}
        </pkp-button>
    </div>
{/block}