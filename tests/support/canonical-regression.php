<?php
/** Exact historical provenance and fail-closed current regression verification. */
final class IDG_Canonical_Regression {
    public const LEGACY_MANIFEST_SHA = '4c913dc042bd5885e3dab5946f72c2b2dd95a4b2683e1dac5d3ff8d27146ac53';
    public const HISTORICAL_MANIFEST_SHA = '21e558259f6d2f53298bb7c0c69b790f0e0315e62bf9e41731f9c9b1ebfc285f';
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
        'includes/class-prompt-library.php' => '419d14b7561a44d18042081c52fa66ccc9976359c8ba87dd0fff915cf84316f1',
        'includes/class-validator.php' => 'd7bb0c4e0d028d8bc274244237bf81a0c56f77ecee5141fd2ffdf7900facadc2',
        'includes/class-final-guard.php' => 'd20e654eaad1f47e4fd534d487ed31a5cbe10280fbfc782386b14eacd82e683f',
        'includes/class-editorial-rules.php' => '8264efe31c780d387af53282b95f66f81da95f98c521a22230cba0a8f4694bfd',
        'includes/class-editorial-plan.php' => '3f8b42d22fa12f2caeb6e837f4412765c13067b2e4cc3d36cc516bafdceeac2d',
        'includes/class-editorial-recipe-builder.php' => 'fe00f7b3524c11d631266e4bffba4a0bb83a57c097bebdcc4b212d027820c04c',
        'includes/class-post-creator.php' => 'bd69c626507539968ba4685695fa320cbee39d9a5f4bee1280f6fe72b47b3380',
    ];
    public const HISTORICAL_PATHS = [
        'REGRESION-EDITORIAL-RC1.6.5.sha256',
        'REGRESION-EDITORIAL-RC1.7.0.sha256',
        'REGRESION-EDITORIAL-RC1.7.1.sha256',
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
        'scripts/test.sh',
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
        'tests/rc171-canonical-fidelity.py',
        'tests/rc171-editorial-consumption.py',
        'tests/rc171-release-integration.py',
        'tests/rc172-release-integration.py',
        'tests/rc172-seo-alignment.py',
        'tests/support/canonical-regression.php',
    ];
    public const CURRENT_PATHS = [
        'REGRESION-EDITORIAL-RC1.6.5.sha256',
        'REGRESION-EDITORIAL-RC1.7.0.sha256',
        'REGRESION-EDITORIAL-RC1.7.1.sha256',
        'REGRESION-EDITORIAL-RC1.7.2.sha256',
        'assets/admin.css',
        'assets/admin.js',
        'ideasdi-redaccion-gerizim.php',
        'includes/class-admin-page.php',
        'includes/class-assignment-card.php',
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
        'includes/class-reel-contract.php',
        'includes/class-validator.php',
        'includes/class-workflow-output-parser.php',
        'includes/class-workflow-prompt-data.php',
        'includes/data/editorial-canonical.php',
        'includes/data/editorial-recipes.php',
        'scripts/test.sh',
        'tests/plugin-version-contract.py',
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
        'tests/rc171-canonical-fidelity.py',
        'tests/rc171-editorial-consumption.py',
        'tests/rc171-release-integration.py',
        'tests/rc172-release-integration.py',
        'tests/rc172-seo-alignment.py',
        'tests/rc173-yoast-contract.py',
        'tests/rc174-links-contract.py',
        'tests/rc175-gutenberg-contract.py',
        'tests/rc175-gutenberg-mock.php',
        'tests/rc176-reel-contract.php',
        'tests/rc176-reel-static.py',
        'tests/support/canonical-regression.php',
    ];

    public static function parse_historical(string $text): array {
        return self::parse_exact($text, self::HISTORICAL_PATHS);
    }

    public static function parse_current(string $text): array {
        return self::parse_exact($text, self::CURRENT_PATHS);
    }

    public static function current_manifest(string $root): array {
        return self::parse_current(self::read($root . '/REGRESION-EDITORIAL-RC1.7.6.sha256'));
    }

    public static function current_files_match(string $root, array $paths): bool {
        try {
            $current = self::current_manifest($root);
        } catch (Throwable $error) {
            return false;
        }
        if (count($paths) !== count(array_unique($paths))) {
            return false;
        }
        foreach ($paths as $path) {
            if (!is_string($path)
                || !array_key_exists($path, $current)
                || !is_file($root . '/' . $path)
                || !hash_equals($current[$path], hash_file('sha256', $root . '/' . $path))) {
                return false;
            }
        }
        return true;
    }

    public static function current_state_matches(string $root, array $current): bool {
        if (!self::is_exact_map($current, self::CURRENT_PATHS)) {
            return false;
        }
        foreach ($current as $path => $expected) {
            if (!is_file($root . '/' . $path)
                || !hash_equals($expected, hash_file('sha256', $root . '/' . $path))) {
                return false;
            }
        }
        return true;
    }

    public static function current_hash_matches(string $path, string $actual, array $current): bool {
        return self::is_exact_map($current, self::CURRENT_PATHS)
            && array_key_exists($path, $current)
            && hash_equals($current[$path], $actual);
    }

    public static function historical_hash_matches(string $path, string $legacy, array $historical): bool {
        if (!self::is_exact_map($historical, self::HISTORICAL_PATHS)
            || (self::LEGACY[$path] ?? null) !== $legacy) {
            return false;
        }
        $expected = self::RECONCILED[$path] ?? $legacy;
        return isset($historical[$path]) && hash_equals($expected, $historical[$path]);
    }

    public static function historical_matches(string $root, string $path, string $legacy): bool {
        $legacy_manifest = $root . '/REGRESION-EDITORIAL-RC1.6.5.sha256';
        $historical_manifest = $root . '/REGRESION-EDITORIAL-RC1.7.2.sha256';
        if (!is_file($legacy_manifest)
            || !is_file($historical_manifest)
            || !hash_equals(self::LEGACY_MANIFEST_SHA, hash_file('sha256', $legacy_manifest))
            || !hash_equals(self::HISTORICAL_MANIFEST_SHA, hash_file('sha256', $historical_manifest))) {
            return false;
        }
        try {
            $historical = self::parse_historical(self::read($historical_manifest));
        } catch (Throwable $error) {
            return false;
        }
        return self::historical_hash_matches($path, $legacy, $historical);
    }

    private static function parse_exact(string $text, array $paths): array {
        if ($text === '' || !str_ends_with($text, "\n") || str_ends_with($text, "\n\n")) {
            throw new RuntimeException('Manifest must end in exactly one newline');
        }
        $entries = [];
        foreach (explode("\n", substr($text, 0, -1)) as $line) {
            if (!preg_match('/^([a-f0-9]{64})  ([A-Za-z0-9_.\/-]+)$/D', $line, $match)
                || isset($entries[$match[2]])) {
                throw new RuntimeException('Malformed or duplicate manifest entry');
            }
            $entries[$match[2]] = $match[1];
        }
        if (!self::is_exact_map($entries, $paths)) {
            throw new RuntimeException('Regression set must be exact and sorted');
        }
        return $entries;
    }

    private static function is_exact_map(array $entries, array $paths): bool {
        if (array_keys($entries) !== $paths) {
            return false;
        }
        foreach ($entries as $hash) {
            if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1) {
                return false;
            }
        }
        return true;
    }

    private static function read(string $path): string {
        $text = file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException('Cannot read regression manifest');
        }
        return $text;
    }
}
