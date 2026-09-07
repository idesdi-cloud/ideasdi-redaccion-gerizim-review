<?php
/** Exact D2-D1 provenance. No blanket acceptance of a changed legacy file. */
final class IDG_Canonical_Regression {
    public const LEGACY_MANIFEST_SHA = '4c913dc042bd5885e3dab5946f72c2b2dd95a4b2683e1dac5d3ff8d27146ac53';
    public const LEGACY = [
        'assets/admin.js' => 'ff0c3c0ba0cccb38103be0452aff75b21b799a14cbb7dede0351b96670a6a350',
        'assets/admin.css' => '257b481a61fd6b031fec33dfa14327b7d1e64f8341cc3454ddca797fd2cf4acd',
        'includes/class-prompt-library.php' => '5535975f1e289e5354dc18bdb374f031ceaeedef99e0ab91234b71dc4d9e022b',
        'includes/class-validator.php' => '7b0e4cca2b089c0a85c98df4d9ab70eae1a5b03a3560cb6050b400933379b485',
        'includes/class-final-guard.php' => '8e4e06c7a4ce47e608ee230b50e36513357aa0682662e79365bce8e8612cca82',
        'includes/class-editorial-rules.php' => 'c77e4a3cb8d73a66377c2ff7f234f38faff5d1122e3df7fd333edeadacf55e1f',
        'includes/class-editorial-plan.php' => '041451207efc560142395ecce51e10d3357155afa6cfb4d5f3382e55c0196ebd',
        'includes/class-editorial-recipe-builder.php' => 'fee28b83033825666a5d4a4c803d226d3f7768c72fafb61f86218d8b26da5e6c',
        'includes/class-post-creator.php' => 'c3c40b9b8ac57d3cd1dbdba184b61f602049602e061faf4f7b0fcb3e1ff15b3d',
        'includes/class-recurring-updates.php' => 'f3bdb6742c07726eae92772e45575439c3c6e06d503c2d1e64e0bff5158b7db4',
    ];
    public const RECONCILED = [
        'includes/class-prompt-library.php' => '11b941573c9a31e73638c35dc2fec565fa6b3e126f6eb6f94ebcb33ffdcedcd6',
        'includes/class-final-guard.php' => 'ad97dc6d190f487d90b21061bbf5a824d505ac22e49182a3b9f4486f4590bdc4',
        'includes/class-editorial-plan.php' => '3f8b42d22fa12f2caeb6e837f4412765c13067b2e4cc3d36cc516bafdceeac2d',
        'includes/class-editorial-recipe-builder.php' => 'fe00f7b3524c11d631266e4bffba4a0bb83a57c097bebdcc4b212d027820c04c',
        'includes/class-post-creator.php' => 'bd69c626507539968ba4685695fa320cbee39d9a5f4bee1280f6fe72b47b3380',
    ];
    public const PATHS = [
        'REGRESION-EDITORIAL-RC1.6.5.sha256',
        'assets/admin.css',
        'assets/admin.js',
        'ideasdi-redaccion-gerizim.php',
        'includes/class-admin-page.php',
        'includes/class-canonical-adapter.php',
        'includes/class-canonical-context.php',
        'includes/class-editorial-plan.php',
        'includes/class-editorial-recipe-builder.php',
        'includes/class-editorial-rules.php',
        'includes/class-final-guard.php',
        'includes/class-internal-links.php',
        'includes/class-post-creator.php',
        'includes/class-prompt-library.php',
        'includes/class-recurring-updates.php',
        'includes/class-validator.php',
        'includes/class-workflow-prompt-data.php',
        'includes/data/editorial-canonical.php',
        'includes/data/editorial-recipes.php',
        'tests/rc160-acceptance.php',
        'tests/rc161-acceptance.php',
        'tests/rc162-acceptance.php',
        'tests/rc163-acceptance.php',
        'tests/rc164-admin-separation-equivalence.php',
        'tests/rc165-legacy-cleanup-equivalence.php',
        'tests/rc170-canonical-consumption.php',
        'tests/rc170-canonical-core.php',
        'tests/rc170-canonical-external-guard.php',
        'tests/rc170-canonical-internal-links.php',
        'tests/rc170-canonical-prompts-admin.php',
        'tests/rc170-release-regression.php',
        'tests/support/canonical-regression.php',
    ];

    public static function parse(string $text): array {
        $entries = [];
        foreach (explode("\n", rtrim($text, "\n")) as $line) {
            if (!preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9_.\/-]+)$/D', $line, $match)
                || isset($entries[$match[2]])) {
                throw new RuntimeException('Malformed or duplicate manifest entry');
            }
            $entries[$match[2]] = $match[1];
        }
        if (array_keys($entries) !== self::PATHS) {
            throw new RuntimeException('Regression set must be exact and sorted');
        }
        return $entries;
    }

    public static function historical_hash_matches(string $path, string $legacy, string $actual, array $current): bool {
        if ((self::LEGACY[$path] ?? null) !== $legacy) {
            return false;
        }
        if (isset(self::RECONCILED[$path])) {
            return $actual === self::RECONCILED[$path]
                && ($current[$path] ?? null) === self::RECONCILED[$path];
        }
        return $actual === $legacy;
    }

    public static function historical_matches(string $root, string $path, string $legacy): bool {
        if (hash_file('sha256', $root . '/REGRESION-EDITORIAL-RC1.6.5.sha256') !== self::LEGACY_MANIFEST_SHA) {
            return false;
        }
        $current = self::parse(file_get_contents($root . '/REGRESION-EDITORIAL-RC1.7.0.sha256'));
        return self::historical_hash_matches($path, $legacy, hash_file('sha256', $root . '/' . $path), $current);
    }
}
