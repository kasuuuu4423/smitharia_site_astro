import js from '@eslint/js';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import astro from 'eslint-plugin-astro';
import importPlugin from 'eslint-plugin-import';
import unicorn from 'eslint-plugin-unicorn';

export default [
  {
    // グローバル設定
    ignores: ['node_modules/**', 'dist/**', '.astro/**', '.github/**', '.vscode/**'],
    linterOptions: {
      reportUnusedDisableDirectives: 'error',
    },
  },
  // JSの基本設定
  {
    files: ['**/*.js', '**/*.mjs', '**/*.cjs'],
    ...js.configs.recommended,
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.es2022,
      },
    },
  },
  // TypeScriptの基本設定
  {
    files: ['**/*.ts', '**/*.tsx', '**/*.astro'],
    plugins: {
      '@typescript-eslint': tseslint.plugin,
    },
    languageOptions: {
      parser: tseslint.parser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        project: './tsconfig.json',
      },
      globals: {
        ...globals.browser,
        ...globals.node,
        ...globals.es2022,
      },
    },
    rules: {
      // 一般的なルール
      'no-console': ['warn', { allow: ['warn', 'error'] }],
      'no-debugger': 'warn',
      'no-duplicate-imports': 'error',
      'no-unused-vars': 'off', // typescript版を使用
      'no-var': 'error',
      'prefer-const': 'error',
      'spaced-comment': ['error', 'always'],

      // TypeScriptルール
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
      '@typescript-eslint/explicit-function-return-type': ['error', { allowExpressions: true }],
      '@typescript-eslint/consistent-type-imports': 'error',
      '@typescript-eslint/ban-ts-comment': ['error', { 'ts-ignore': 'allow-with-description' }],
      '@typescript-eslint/no-floating-promises': 'error',
      '@typescript-eslint/no-misused-promises': 'error',
    },
  },
  // Astro設定
  {
    files: ['**/*.astro'],
    plugins: {
      astro: astro,
    },
    processor: astro.processors.astro,
    languageOptions: {
      parser: astro.parser,
    },
    rules: {
      // astroのルール
    },
  },
  // Unicornプラグイン設定
  {
    plugins: {
      unicorn: unicorn,
    },
    rules: {
      'unicorn/prevent-abbreviations': 'off',
      'unicorn/no-null': 'off',
      'unicorn/filename-case': [
        'error',
        {
          cases: {
            kebabCase: true,
            pascalCase: true,
          },
        },
      ],
    },
  },
  // インポート設定
  {
    plugins: {
      import: importPlugin,
    },
    settings: {
      'import/resolver': {
        node: {
          extensions: ['.js', '.jsx', '.ts', '.tsx', '.astro'],
        },
      },
    },
    rules: {
      'import/no-unresolved': 'off',
      'import/order': [
        'error',
        {
          groups: ['builtin', 'external', 'internal', 'parent', 'sibling', 'index', 'object', 'type'],
          'newlines-between': 'always',
          alphabetize: {
            order: 'asc',
            caseInsensitive: true,
          },
        },
      ],
    },
  },
]; 