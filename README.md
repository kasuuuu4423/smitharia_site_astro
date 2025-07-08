# smitharia サイト

クリエイティブプラットフォーム「smitharia（スミサリア）」のオフィシャルウェブサイト

## プロジェクト概要

smithariaは、メンバーを固定せず、その時必要な人が集まりモノを作るクリエイティブプラットフォームです。映像・音楽などのメディアを中心に様々な作品を創出しています。

## 技術スタック

### フロントエンド
- **Astro 4.2** - 静的サイトジェネレーター
- **React 18** - インタラクティブコンポーネント
- **TypeScript** - 型安全性の確保
- **Tailwind CSS** - スタイリング

### バックエンド・CMS
- **WordPress** - コンテンツ管理システム（REST API）
- データソース: `https://smitharia.shimizuyasushi.com`

### デプロイメント・ツール
- **Firebase Hosting** - ホスティング
- **ESLint** - コード品質チェック
- **Prettier** - コードフォーマット
- **Husky** - Git hooks

## プロジェクト構造

```
src/
├── components/          # コンポーネント
│   ├── common/         # 共通コンポーネント
│   ├── works/          # 作品関連コンポーネント
│   ├── top/            # トップページコンポーネント
│   └── aboutus/        # about usページコンポーネント
├── layouts/            # レイアウトテンプレート
├── lib/               # ライブラリ・ユーティリティ
├── pages/             # ページファイル
└── assets/            # 静的アセット
```

## 主要機能

### 1. 作品ポートフォリオ表示
- WordPressから作品データを取得
- カテゴリ別フィルタリング機能
- おすすめ作品のスライダー表示
- レスポンシブデザイン

### 2. 限定公開機能
- パブリック版（`/`）と限定版（`/limited/`）の二つのバージョン
- 限定作品の表示切り替え
- SEO設定（限定版は`noindex`）

### 3. カテゴリシステム
- works（作品）
- studies（研究）
- artists（メンバータグ）

### 4. メンバー管理
- WordPressでメンバー情報を管理
- プロフィール画像とバイオグラフィー表示

## セットアップ

### 必要な環境
- Node.js 18以上
- npm または yarn

### インストール
```bash
# リポジトリをクローン
git clone [repository-url]
cd smitharia-site

# 依存関係をインストール
npm install
```

### 開発環境の起動
```bash
# 開発サーバーを起動（ホスト0.0.0.0で起動）
npm run dev

# または
npm start
```

開発サーバーは `http://localhost:4321` で起動します。

### ビルド
```bash
# プロダクション用ビルド
npm run build

# プレビュー
npm run preview
```

## デプロイメント

### Firebase Hosting
```bash
# ビルド実行
npm run build

# Firebaseプロジェクトへデプロイ
firebase deploy
```

**設定ファイル:**
- `.firebaserc` - プロジェクト設定
- `firebase.json` - ホスティング設定

## コード品質

### リンティングとフォーマット
```bash
# ESLintチェック
npm run lint:precommit

# Prettierフォーマットチェック
npm run fmt:precommit
```

### Git Hooks
- pre-commit時に自動でlintとフォーマットチェックが実行されます
- `husky`と`lint-staged`により管理

## 設定ファイル

### Astro設定 (`astro.config.mjs`)
- React integration
- Tailwind CSS integration
- Sitemap生成
- Partytown（アナリティクス最適化）
- 画像ドメイン設定

### Tailwind設定 (`tailwind.config.mjs`)
- カスタムフォント（big-caslon-fb, 游ゴシック）
- ブランドカラー（red, green, blue）

## API連携

### WordPress REST API
WordPressのREST APIエンドポイント:
- 作品: `/wp-json/wp/v2/works`
- カテゴリ: `/wp-json/wp/v2/categories`
- メンバー: `/wp-json/wp/v2/members`
- 設定: `/wp-json/wp/v2/preferences`

### データ型定義
主要なインターフェース:
- `Work` - 作品データ
- `CatType` - カテゴリデータ
- `MemberType` - メンバーデータ

## ページ構成

### パブリック版
- `/` - トップページ（作品一覧）
- `/[catSlug]` - カテゴリ別作品一覧
- `/work/[id]` - 作品詳細
- `/aboutus` - 会社概要・メンバー紹介

### 限定版
- `/limited/` - 限定版トップページ
- `/limited/[catSlug]` - 限定版カテゴリ別一覧
- `/limited/work/[id]` - 限定版作品詳細
- `/limited/aboutus` - 限定版会社概要

## ライセンス

MIT License - 詳細は `LICENSE` ファイルを参照してください。

## コンタクト

- Website: https://smitharia.com
- Twitter: [@__smitharia](https://twitter.com/__smitharia)
- Instagram: [@__smitharia](https://www.instagram.com/__smitharia)
- Email: info@smitharia.com

---

© smitharia.com