<?php

/**
 * @file classes/migration/install/InvitationsMigration.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InvitationsMigration
 *
 * @brief Changes for the access_keys table to support invitations.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InvitationsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
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

            // Add Table Indexes

            // Invitations
            $table->index(['status', 'context_id', 'user_id', 'type']);

            // Expired
            $table->index(['expiry_date']);
        });
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::drop('invitations');
    }
}
