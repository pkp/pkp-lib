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
use PKP\core\PKPApplication;
use PKP\install\DowngradeNotSupportedException;
use PKP\invitation\invitations\RegistrationAccessInvite;
use PKP\invitation\invitations\ReviewerAccessInvite;
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
            $table->string('key_hash', 255);

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('cascade');

            $table->index(['user_id'], 'invitations_user_id');

            $table->bigInteger('assoc_id')->nullable();
            $table->datetime('expiry_date');
            $table->json('payload')->nullable();

            // The values are references to the enum InvitationStatus 
            // InvitationStatus::PENDING, InvitationStatus::ACCEPTED, InvitationStatus::DECLINED
            // InvitationStatus::EXPIRED, InvitationStatus::CANCELLED
            $table->enum('status', 
                [
                    'PENDING', 
                    'ACCEPTED', 
                    'DECLINED', 
                    'EXPIRED', 
                    'CANCELLED'
                ]
            );
            $table->string('class_name');
            $table->string('email')->nullable()->comment('When present, the email address of the invitation recipient; when null, user_id must be set and the email can be fetched from the users table.');

            $table->bigInteger('context_id')->nullable();

            $contextDao = \APP\core\Application::getContextDAO();
            $table->foreign('context_id', 'invitations_context_id')
                ->references($contextDao->primaryKeyColumn)
                ->on($contextDao->tableName)
                ->onDelete('cascade');

            $table->timestamps();

            // Add Table custom invitations Indexes

            // RegistrationAccessInvite
            $table->index(['status', 'context_id', 'user_id', 'class_name']);

            // ReviewerAccessInvite
            $table->index(['status', 'context_id', 'user_id', 'class_name', 'assoc_id']);
        });

        $accessKeys = DB::table('access_keys')
            ->get();

        foreach ($accessKeys as $accessKey) {
            $invitation = null;
            
            if ($accessKey->context == 'RegisterContext') { // Registered User validation Invitation
                $invitation = new RegistrationAccessInvite($accessKey->user_id);
            } else if (isset($accessKey->context)) { // Reviewer Invitation
                $invitation = new ReviewerAccessInvite(
                    $accessKey->user_id, 
                    $accessKey->context, 
                    $accessKey->assoc_id
                );
            }

            if (isset($invitation)) {
                $invitation->setKeyHash($accessKey->key_hash);
                $invitation->setExpirationDate(new Carbon($accessKey->expiry_date));

                $invitation->dispatch();

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
