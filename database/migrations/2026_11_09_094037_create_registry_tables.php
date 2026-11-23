<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HEMIS'dan olinadigan ma'lumotlar uchun reyestr jadvallari.
     * Klassifikator qiymatlari (kod + nom) tezkor statistika uchun to'g'ridan-to'g'ri saqlanadi,
     * asl yozuv esa `raw` (jsonb) ustunida manba sifatida qoladi.
     */
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary(); // HEMIS ID
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('structure_type_code', 8)->nullable();
            $table->string('structure_type_name')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('specialties', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('code')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('education_type_code', 8)->nullable();
            $table->string('education_type_name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('education_lang_name')->nullable();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->unsignedBigInteger('meta_id')->nullable();
            $table->string('student_id_number')->nullable()->index();
            $table->string('full_name')->index();
            $table->string('first_name')->nullable();
            $table->string('second_name')->nullable();
            $table->string('third_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('image')->nullable();

            // Klassifikatorlar: *_code + *_name
            foreach ([
                'gender', 'country', 'province', 'district', 'citizenship', 'status',
                'education_form', 'education_type', 'payment_form', 'grant_type', 'student_type',
                'social_category', 'accommodation', 'level', 'education_year', 'education_lang',
                'admission_type', 'transfer_type', 'academic_mobile_type',
            ] as $classifier) {
                $table->string($classifier.'_code', 16)->nullable();
                $table->string($classifier.'_name')->nullable();
            }

            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->unsignedBigInteger('specialty_id')->nullable();
            $table->string('specialty_code')->nullable();
            $table->string('specialty_name')->nullable();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('group_name')->nullable();
            $table->unsignedBigInteger('curriculum_id')->nullable();
            $table->unsignedSmallInteger('year_of_enter')->nullable();
            $table->decimal('avg_gpa', 5, 2)->nullable();
            $table->boolean('is_graduate')->default(false);
            $table->string('transfer_university')->nullable();
            $table->timestamp('hemis_created_at')->nullable();
            $table->timestamp('hemis_updated_at')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamps();

            $table->index('status_code');
            $table->index('department_id');
            $table->index('year_of_enter');
            $table->index(['education_type_code', 'education_form_code']);
            $table->index(['full_name', 'birth_date']);
        });

        Schema::create('diplomas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->string('uid')->nullable();
            $table->unsignedBigInteger('student_hemis_id')->nullable()->index();
            $table->string('diploma_number')->nullable()->index();
            $table->string('register_number')->nullable();
            $table->date('register_date')->nullable();
            $table->decimal('avg_grade', 5, 2)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->string('group_name')->nullable();
            $table->string('specialty_code')->nullable();
            $table->string('specialty_name')->nullable();
            $table->string('education_type_code', 16)->nullable();
            $table->string('education_type_name')->nullable();
            $table->string('education_form_code', 16)->nullable();
            $table->string('education_form_name')->nullable();
            $table->string('category_code', 16)->nullable();
            $table->string('category_name')->nullable();
            $table->date('enroll_date')->nullable();
            $table->date('graduate_date')->nullable();
            $table->unsignedSmallInteger('graduate_year')->nullable()->index();
            $table->unsignedSmallInteger('education_period')->nullable();
            $table->boolean('accepted')->default(false);
            $table->boolean('published')->default(false);
            $table->string('diploma_file')->nullable();
            $table->jsonb('raw')->nullable();
            $table->timestamps();
        });

        Schema::create('student_gpas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hemis_id')->unique();
            $table->unsignedBigInteger('student_hemis_id')->nullable()->index();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('education_year_code', 8)->nullable()->index();
            $table->string('education_year_name')->nullable();
            $table->string('level_code', 8)->nullable();
            $table->string('level_name')->nullable();
            $table->decimal('gpa', 4, 2)->nullable();
            $table->decimal('credit_sum', 7, 1)->nullable();
            $table->unsignedSmallInteger('subjects')->nullable();
            $table->unsignedSmallInteger('debt_subjects')->nullable();
            $table->boolean('can_transfer')->nullable();
            $table->timestamps();
        });

        // Import partiyalari (PDF: "Import batch") — har bir sinxronizatsiya jurnali
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('entity');
            $table->string('status')->default('processing'); // processing | success | error
            $table->unsignedInteger('pages_done')->default(0);
            $table->unsignedInteger('pages_total')->default(0);
            $table->unsignedInteger('records')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['sync_logs', 'student_gpas', 'diplomas', 'students', 'groups', 'specialties', 'departments'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
