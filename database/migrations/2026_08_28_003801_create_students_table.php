<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->id();

            $table
                ->uuid('uuid')
                ->unique();

            /*
             * Futuramente a fotografia será armazenada
             * em storage privado.
             *
             * O banco guarda apenas o caminho do arquivo.
             */
            $table
                ->string('photo_path', 1000)
                ->nullable();

            /*
             * Identificação
             */
            $table
                ->string('name', 180)
                ->index();

            $table
                ->date('birth_date')
                ->nullable()
                ->index();

            $table
                ->string('sex', 30)
                ->nullable()
                ->index();

            /*
             * Endereço
             */
            $table
                ->string('address', 255)
                ->nullable();

            $table
                ->string('address_number', 30)
                ->nullable();

            $table
                ->string('address_complement', 120)
                ->nullable();

            $table
                ->string('neighborhood', 120)
                ->nullable();

            $table
                ->string('city', 120)
                ->nullable()
                ->index();

            $table
                ->char('state', 2)
                ->nullable()
                ->index();

            /*
             * Contato
             */
            $table
                ->string('mobile_phone', 30)
                ->nullable();

            $table
                ->string('home_phone', 30)
                ->nullable();

            $table
                ->string('email', 255)
                ->nullable()
                ->index();

            /*
             * Situação
             */
            $table
                ->boolean('active')
                ->default(true)
                ->index();

            /*
             * Observações exclusivamente administrativas.
             *
             * Dados clínicos ficarão vinculados às avaliações
             * e não devem ser misturados neste campo.
             */
            $table
                ->text('administrative_notes')
                ->nullable();

            /*
             * Arquivamento lógico.
             *
             * Não vamos excluir fisicamente alunos que possuam
             * histórico de avaliações.
             */
            $table
                ->timestamp('archived_at')
                ->nullable()
                ->index();

            /*
             * Auditoria básica de criação e alteração.
             */
            $table
                ->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table
                ->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            /*
             * Índices utilizados nas consultas mais comuns.
             */
            $table->index([
                'active',
                'name',
            ]);

            $table->index([
                'active',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
