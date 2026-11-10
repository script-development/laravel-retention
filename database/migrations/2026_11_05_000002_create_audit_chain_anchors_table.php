<?php

declare(strict_types = 1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_chain_anchors', function(Blueprint $table): void {
            $table->id();
            $table->string('chain')->unique();
            $table->unsignedBigInteger('through_id');
            $table->char('anchor_hash', 64);
            $table->char('mac', 64);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_chain_anchors');
    }
};
