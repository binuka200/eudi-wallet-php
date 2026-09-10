<?php

declare(strict_types=1);

namespace EudiWallet\Tests;

use EudiWallet\Claim;
use EudiWallet\Exception\InvalidWalletResponse;
use EudiWallet\Identity\PidClaimValidator;
use PHPUnit\Framework\TestCase;

final class PidClaimValidatorTest extends TestCase
{
    public function testAcceptsRulebookValueTypes(): void
    {
        PidClaimValidator::validate([
            Claim::FAMILY_NAME => 'Dupont',
            Claim::GIVEN_NAME => 'Jean',
            Claim::BIRTH_DATE => '1980-05-23',
            Claim::BIRTH_PLACE => ['country' => 'FR', 'locality' => 'Paris'],
            Claim::NATIONALITY => ['FR', 'QU'],
            Claim::PORTRAIT => 'data:image/jpeg;base64,/9j/4AAQSkZJRg==',
            Claim::RESIDENT_ADDRESS => '1 Rue de Rivoli, Paris',
            Claim::RESIDENT_COUNTRY => 'FR',
            Claim::RESIDENT_STATE => 'Île-de-France',
            Claim::RESIDENT_CITY => 'Paris',
            Claim::RESIDENT_POSTAL_CODE => '75001',
            Claim::RESIDENT_STREET => 'Rue de Rivoli',
            Claim::RESIDENT_HOUSE_NUMBER => '1',
            Claim::PERSONAL_ADMINISTRATIVE_NUMBER => '123456789',
            Claim::FAMILY_NAME_BIRTH => 'Dupont',
            Claim::GIVEN_NAME_BIRTH => 'Jean',
            Claim::SEX => 5,
            Claim::EMAIL => 'jean@example.com',
            Claim::MOBILE_PHONE_NUMBER => '+33123456789',
            Claim::ISSUING_AUTHORITY => 'FR',
            Claim::ISSUING_COUNTRY => 'FR',
            Claim::EXPIRY_DATE => '2035-12-19',
            Claim::DOCUMENT_NUMBER => 'A01234567',
            Claim::ISSUING_JURISDICTION => 'FR-IDF',
            Claim::ISSUANCE_DATE => '2026-09-10T12:30:00Z',
            Claim::TRUST_ANCHOR => 'https://example.test/trust',
            Claim::ATTESTATION_LEGAL_CATEGORY => 'PID',
        ]);

        $this->addToAssertionCount(1);
    }

    public function testRejectsPortraitThatIsNotABase64DataUrl(): void
    {
        foreach (['', "\xFF\xD8\xFF", 'data:image/jpeg;base64,', 'https://example.test/me.jpg'] as $portrait) {
            try {
                PidClaimValidator::validate([Claim::PORTRAIT => $portrait]);
                $this->fail('Portrait should have been rejected.');
            } catch (InvalidWalletResponse $exception) {
                $this->assertStringContainsString('portrait', $exception->getMessage());
            }
        }
    }

    public function testRejectsMalformedStructuredAndContactValues(): void
    {
        $this->expectException(InvalidWalletResponse::class);
        $this->expectExceptionMessage('birth_place, email_address, mobile_phone_number');

        PidClaimValidator::validate([
            Claim::BIRTH_PLACE => [],
            Claim::EMAIL => 'not-an-email',
            Claim::MOBILE_PHONE_NUMBER => '0033123456789',
        ]);
    }
}
