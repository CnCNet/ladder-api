<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateIdentsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('idents', function(Blueprint $table)
		{
            $table->increments('id');
            $table->string('identifier');
			$table->integer('user_id');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::table('idents', function(Blueprint $table)
		{
            $table->dropColumn('id');
            $table->dropColumn('identifier');
            $table->dropColumn('user_id');
		});
	}

}
