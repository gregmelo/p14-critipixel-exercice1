<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude([
        'var',
        'vendor',
        'node_modules',
        'public',
        'assets',
    ])
    ->notPath([
        'config/bundles.php',
    ])
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Standard Symfony (inclut PSR-1 et PSR-12)
        '@Symfony'       => true,
        '@Symfony:risky' => true,

        // declare(strict_types=1) obligatoire sur chaque fichier PHP
        'declare_strict_types' => true,

        // Imports : un use par ligne, triés alphabétiquement, sans lignes vides entre groupes
        'ordered_imports'           => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'         => true,
        'single_import_per_statement' => true,

        // Cohérence des tableaux : syntaxe courte []
        'array_syntax' => ['syntax' => 'short'],

        // Opérateurs : espacement cohérent autour de = != etc.
        'binary_operator_spaces' => ['default' => 'single_space'],

        // Trailing comma dans les listes multi-lignes
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'arguments', 'parameters']],

        // Accolades sur la même ligne pour les fonctions/classes (PSR-12)
        'braces_position' => [
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'classes_opening_brace'   => 'next_line_unless_newline_at_signature_end',
        ],

        // Concaténation : espaces autour du point
        'concat_space' => ['spacing' => 'one'],

        // Blank lines : pas plus d'une ligne vide consécutive
        'no_extra_blank_lines' => ['tokens' => ['extra', 'throw', 'use']],

        // Retour à la ligne en fin de fichier
        'single_blank_line_at_eof' => true,

        // Cast : espace après le cast (ex: (int) $val)
        'cast_spaces' => ['space' => 'single'],
    ])
    ->setFinder($finder)
;
