<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Fixtures\Api;

// ---------------------------------------------------------------------------
// Full-featured interface covering every attribute
// ---------------------------------------------------------------------------

use Tugrul\ApiGen\Attributes\Method\{GET, PATCH, POST, PUT, DELETE};
use Tugrul\ApiGen\Attributes\Params\{Body, Field, Header, Part, Path, Query, QueryMap};
use Tugrul\ApiGen\Attributes\Modifiers\{ApiNamespace, BaseUrl, FormUrlEncoded, Headers, Multipart,
    NoAuth, Returns, StaticHeader, UseAuth};

#[ApiNamespace('pets')]
#[StaticHeader('Accept', 'application/json')]
#[Headers(['X-Client' => 'apigen'])]
interface PetApi
{
    #[GET('/pets')]
    #[Returns('array')]
    #[NoAuth]
    public function listPets(
        #[Query] int $page = 1,
        #[Query('per_page')] int $perPage = 20,
        #[QueryMap] array $filters = [],
    ): array;

    #[GET('/pets/{petId}')]
    #[Returns('array')]
    public function getPet(
        #[Path] int $petId,
    ): array;

    #[POST('/pets')]
    #[Returns('array')]
    public function createPet(
        #[Body] array $data,
    ): array;

    #[PUT('/pets/{petId}')]
    #[Returns('array')]
    public function updatePet(
        #[Path] int $petId,
        #[Body] array $data,
    ): array;

    #[PATCH('/pets/{petId}')]
    #[Returns('array')]
    public function patchPet(
        #[Path] int $petId,
        #[Body] array $data,
    ): array;

    #[DELETE('/pets/{petId}')]
    public function deletePet(
        #[Path] int $petId,
    ): void;

    #[POST('/pets/upload')]
    #[Multipart]
    #[Returns('array')]
    public function uploadPhoto(
        #[Part] string $photo,
        #[Part('meta')] string $metadata,
    ): array;

    #[POST('/pets/register')]
    #[FormUrlEncoded]
    #[Returns('array')]
    public function registerPet(
        #[Field] string $name,
        #[Field('pet_type')] string $type,
    ): array;

    #[GET('/pets/search')]
    #[StaticHeader('X-Search-Mode', 'fast')]
    #[Returns('array')]
    public function searchPets(
        #[Header('X-Locale')] string $locale,
        #[Query] ?string $q = null,
    ): array;

    #[GET('/pets/{petId}/records')]
    #[BaseUrl('https://records.example.com')]
    #[Returns('array')]
    public function getMedicalRecords(
        #[Path] int $petId,
    ): array;

    #[GET('/pets/admin')]
    #[UseAuth('Tugrul\ApiGen\Tests\Fixtures\Auth\FakeAdminAuth')]
    #[Returns('array')]
    public function adminList(): array;
}

