<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Modules\Accounting\Support\TenantSchema as Schema;
return new class extends Migration {
 protected $connection='tenant';
 public function up():void {
  Schema::create('accounting_user_security',function(Blueprint $t){
   $t->unsignedInteger('user_id')->primary();$t->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
   $t->text('totp_secret')->nullable();$t->text('pending_secret')->nullable();$t->dateTime('pending_until')->nullable();$t->json('recovery_codes')->nullable();$t->bigInteger('last_counter')->default(-1);$t->timestamps();
  });
  Schema::create('accounting_auth_grants',function(Blueprint $t){
   $t->uuid('id')->primary();$t->unsignedInteger('user_id');$t->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
   $t->string('pro9_token_hash',64);$t->string('verification_hash',64)->unique();$t->dateTime('expires_at');$t->timestamps();$t->index(['user_id','expires_at']);
  });
 }
 public function down():void {Schema::dropIfExists('accounting_auth_grants');Schema::dropIfExists('accounting_user_security');}
};
