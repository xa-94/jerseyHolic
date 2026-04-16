<?php

namespace Database\Factories;

use App\Models\Central\Domain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Domain>
 */
class DomainFactory extends Factory
{
    protected $model = Domain::class;

    public function definition(): array
    {
        return [
            'domain'             => $this->faker->unique()->domainName(),
            'certificate_status' => Domain::CERT_PENDING,
        ];
    }

    /**
     * 证书已签发
     */
    public function withCertificate(): static
    {
        return $this->state(fn () => ['certificate_status' => Domain::CERT_ACTIVE]);
    }
}
