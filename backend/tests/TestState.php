<?php

/**
 * Shared static state passed between ordered test files.
 * Tests populate these fields; later tests consume them.
 */
class TestState
{
    // Determined dynamically via DatabaseSetupTest.php
    public static int    $universityId       = 0;
    public static string $universityName     = '';
    public static string $universityCode     = '';
    public static int    $userId             = 0;
    public static int    $studentId          = 0;
    public static string $studentEmail       = '';
    public static string $universityEmail    = '';
    public static string $adminEmail         = '';
    public static string $certificateId      = '';
    public static string $onchainHash        = '';
    public static string $pdfPath            = '';
    public static string $metadataHash       = '';
    public static string $pdfHash            = '';
    public static string $studentJwt         = '';
    public static string $universityJwt      = '';
    public static string $adminJwt           = '';
    public static string $keyFingerprint     = '';
    public static string $blockchainMode     = '';
    public static string $uploadedCertId     = '';
}
