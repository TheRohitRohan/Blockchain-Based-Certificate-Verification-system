<?php

/**
 * Shared static state passed between ordered test files.
 * Tests populate these fields; later tests consume them.
 */
class TestState
{
    // Using seeded data from cleanup_and_seed.php
    public static int    $universityId       = 0;
    public static string $universityName     = 'Global Institute of Technology';
    public static string $universityCode     = 'GIT';
    public static int    $userId             = 0;
    public static int    $studentId          = 0;
    public static string $studentEmail       = 'gitstd001@git.edu';
    public static string $studentPassword    = 'Student@123!';
    public static string $universityEmail    = 'admin@git.edu';
    public static string $universityPassword = 'Student@123!';
    public static string $adminEmail         = 'admin@system.com';
    public static string $adminPassword      = 'Admin@123456';
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
