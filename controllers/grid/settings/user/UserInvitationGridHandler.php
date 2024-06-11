<?php

namespace PKP\controllers\grid\settings\user;

use APP\facades\Repo;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\feature\PagingFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\VirtualArrayIterator;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class UserInvitationGridHandler extends GridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['fetchGrid','addUser']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        return [new PagingFeature()];
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);
        // Basic grid configuration.
        $this->setTitle('grid.user.currentInvitations');
        $context = $request->getContext();

        // Grid actions.
        $router = $request->getRouter();

        $this->addAction(
            new LinkAction(
                'inviteToRole',
                new RedirectAction($router->getDispatcher()->url($request, PKPApplication::ROUTE_PAGE, $context->getData('urlPath'), 'management', 'settings', 'invitations')),
                __('grid.action.inviteToARole'),
                'wrench'
            )
        );

        //
        // Grid columns.
        //
        $cellProvider = new DataObjectGridCellProvider();

        // Email.
        $this->addColumn(
            new GridColumn(
                'email',
                'invitation.email',
                null,
                null,
                $cellProvider
            )
        );

        // expiration date.
        $this->addColumn(
            new GridColumn(
                'expirationDate',
                'invitation.expiryDate',
                null,
                null,
                $cellProvider
            )
        );
        // status
        $this->addColumn(
            new GridColumn(
                'status',
                'invitation.status',
                null,
                null,
                $cellProvider
            )
        );
    }
    /**
     * @copydoc GridHandler::loadData()
     *
     * @param PKPRequest $request
     *
     * @return VirtualArrayIterator Grid data.
     */
    protected function loadData($request, $filter)
    {
        $collector = Repo::invitation();
        //        // Handle grid paging (deprecated style)
        $rangeInfo = $this->getGridRangeInfo($request, $this->getId());
        $totalCount = $collector->total();
        $collector->limit($rangeInfo->getCount());
        $collector->offset($rangeInfo->getOffset() + max(0, $rangeInfo->getPage() - 1) * $rangeInfo->getCount());
        $iterator = $collector->getMany();
        return new VirtualArrayIterator(iterator_to_array($iterator, true), $totalCount, $rangeInfo->getPage(), $rangeInfo->getCount());
    }
}
