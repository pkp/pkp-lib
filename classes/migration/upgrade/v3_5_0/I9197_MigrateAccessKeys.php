<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9197_MigrateAccessKeys.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9197_MigrateAccessKeys
 *
 * @brief Convert access keys to invitations.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\invitation\invitations\registrationAccess\RegistrationAccessInvite;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\migration\Migration;

class I9197_MigrateAccessKeys extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->comment('Invitations are sent to request a person (by email) to allow them to accept or reject an operation or position, such as a board membership or a submission peer review.');
            $table->bigInteger('invitation_id')->autoIncrement();
            $table->string('key_hash', 255)->nullable();
            $table->string('type', 255);

            $table->bigInteger('user_id')->nullable();
            $table->bigInteger('inviter_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'invitations_user_id');
            $table->foreign('inviter_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['inviter_id'], 'invitations_inviter_id');

            $table->datetime('expiry_date')->nullable();
            $table->json('payload')->nullable();

            $table->enum(
                'status',
                [
                    'INITIALIZED',
                    'PENDING',
                    'ACCEPTED',
                    'DECLINED',
                    'CANCELLED',
                ]
            );

            $table->string('email')->nullable()->comment('When present, the email address of the invitation recipient; when null, user_id must be set and the email can be fetched from the users table.');

            $table->bigInteger('context_id')->nullable();

            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id', 'invitations_context_id')
                ->references($contextDao->primaryKeyColumn)
                ->on($contextDao->tableName)
                ->onDelete('cascade');

            $table->timestamps();

            // Add Table custom invitations Indexes

            // Invitations
            $table->index(['status', 'context_id', 'user_id']);

            // Expired
            $table->index(['expiry_date']);
        });

        $accessKeys = DB::table('access_keys')
            ->get();

        foreach ($accessKeys as $accessKey) {
            $invitation = null;

            if ($accessKey->context == 'RegisterContext') { // Registered User validation Invitation
                $invitation = new RegistrationAccessInvite();
                $invitation->initialize($accessKey->user_id, null, null, null);
            } elseif (isset($accessKey->context)) { // Reviewer Invitation
                $invitation = new ReviewerAccessInvite();
                $invitation->initialize($accessKey->user_id, $accessKey->context, null, null);

                $invitation->reviewAssignmentId = $accessKey->assoc_id;
                $invitation->updatePayload();
            }

            if (isset($invitation)) {
                $invitation->invitationModel->keyHash = $accessKey->key_hash;
                $invitation->setExpiryDate(new Carbon($accessKey->expiry_date));

                $invitation->invite();

                DB::table('access_keys')
                    ->where('access_key_id', $accessKey->access_key_id)
                    ->delete();
            }
        }

        Schema::dropIfExists('access_keys');
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
