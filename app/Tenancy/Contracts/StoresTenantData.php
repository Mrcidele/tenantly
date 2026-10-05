<?php

declare(strict_types=1);

namespace App\Tenancy\Contracts;

/**
 * Marca models de dados de domínio que podem ser movidos para um banco
 * dedicado (ver DedicatedDatabaseStrategy). Models sem este marcador
 * (memberships, assinaturas, auditoria) ficam sempre no banco central.
 */
interface StoresTenantData {}
