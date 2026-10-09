# Smitharia Core

Smitharia 固有の WordPress 処理を Git 管理するためのプラグインです。

## 機能

- 画像アップロード時はattachment IDだけをキューへ入れ、WordPress Cronが
  `uploads/filtered/YYYY/MM/filtered-{filename}` をバックグラウンド生成
- 生成は最大3回まで自動再試行し、重複実行をロックで防止
- 「ツール → Smitharia Images」で待機・実行・完了・失敗件数を確認
- 「ツール → Smitharia Images」から既存画像を5件ずつ再生成
- `/wp/v2/posts?is_recommend=true` の安全なおすすめ絞り込み
- ACF 標準 REST API で作品・プロフィール・サイト設定の既存データ形式を維持
- `/wp/v2/posts?limited=exclude|include|only` の限定表示絞り込み
- 「ツール → 限定公開の共有先」で共有先ごとのID・パスワード・有効／無効を管理
- 限定作品の未認証REST取得・公開クエリを制限し、Firebase Functionsからの認証照会に対応
- 投稿更新時に GitHub の `repository_dispatch` を実行

限定公開の初回導入と接続キーの設定は、リポジトリの [限定公開の認証](../../../../docs/limited-access.md) を参照してください。

## ACF REST API の移行

ACF 本体の REST API を使います。`ACF to REST API` はこの処理の導入後に無効化してください。
画像は ACF の `standard` 形式で返し、Preference は既存グループ
`group_65e7715d64eec` の `about_image` と `about_description` だけを公開します。
作品・プロフィールも公開フィールドを明示し、未保存項目は旧 API と同様に省略します。
フィールド追加時は `Smitharia_ACF_REST_API::FIELDS` も更新してください。
WordPress/ACF 本体の書き込み権限チェックを使用し、独自の更新 API は追加しません。

## 作品詳細の日英併記

プラグインを反映すると、投稿の編集画面に「作品詳細 / Project details」が追加されます。
ACF本体が有効になっている必要があります。既存フィールドを作り直す必要はありません。

| 内容 | 日本語 | 英語 |
| --- | --- | --- |
| 一言紹介 | `project_summary` | `project_summary_en` |
| 担当範囲 | `project_scope` | `project_scope_en` |
| 制作の詳細 | `project_approach` | `project_approach_en` |
| 相談できること | `project_consultation` | `project_consultation_en` |

追加欄はHTMLを含まないテキストとして入力します。改行は表示に反映されます。
すべて任意で、片方の言語だけの入力も可能です。両方空欄の説明セクションは表示しません。
自動翻訳は行いません。英語は `lang="en"` を指定して日本語の下に表示します。

英語タイトルは `project_title_en` に入力できます。作品説明とクレジットは既存の
`description`・`credit`を保持し、必要に応じて `description_en`・`credit_en` を追加できます。
既存欄に英語を併記済みなら、英語の追加欄は空欄にしてください。両方に同じ内容を入れると重複表示になります。
写真・映像などの投稿本文は引き続きWordPressの本文エディターで編集します。
本文中のキャプションなども、必要に応じて日本語と英語を併記してください。

「相談できること」には、作品を参考に新しい企画を考える方に向けて、依頼を受けられる制作内容を記入します。
たとえば「MVやライブで音と光を連動させたい場合、演出に合わせたシステムの開発・設置をご相談いただけます。」のように入力します。
作品自体への問い合わせを促す文章や、制作時の振り返りはこの欄に入れません。

公開・限定公開の詳細ページは同じ表示コンポーネントを使います。相談リンクは
「制作の相談をする / Discuss your project」と表示します。
相談メールの件名は「制作のご相談 / Project inquiry」、本文には作品名を「参考作品 / Reference work」として入れ、
続けて新しい企画の相談内容を書けるようにします。
WordPressのプラグイン反映後、フロントエンドを再ビルド・公開すると更新した内容が表示されます。

既存の公開作品45件は、管理画面の「ツール → 作品詳細の初期入力」で日英の紹介と確認できる担当範囲をまとめて入力できます。
元の説明・クレジットに基づく初期文章は `data/project-details.json` に保存しています。
入力済みの追加項目を上書きせず、元の説明・本文・画像・クレジット・公開状態は保持します。
書き込み前の値は `smitharia_project_defaults_backup` オプションへ保存します。
今後の作品や限定公開の作品でも、相談案内が空欄なら共通の日英案内を表示します。
ローカル専用の作品サンプルは使用せず、開発・本番ともにWPの保存値を表示します。

境界テスト: `php wordpress/wp-content/plugins/smitharia-core/tests/acf-rest-api-test.php`
本番切り替え時は公開 API の画像・本文・プロフィールを切り替え前後で比較し、
未認証の更新拒否と旧 `/acf/v3/` ルートの消失を確認してください。

## WordPress の公開画面・コメント

WordPress 側の通常ページ・投稿・検索・フィード・サイトマップは404にします。
管理画面・ログイン・REST API・画像ファイル・Cron は引き続き利用できます。
新規コメント・トラックバック・ピンバックは受付を停止し、既存コメントは削除しません。
コメント REST API の読み取りはコメント管理権限を持つユーザーだけに限定します。
REST API を含むコメントの新規作成も拒否します。

境界テスト: `php wordpress/wp-content/plugins/smitharia-core/tests/headless-test.php`

## GitHub token

トークンをプラグインやGitへ書かないでください。ホスト固有の安全な場所へ保存し、
`wp-config.php` の `SMITHARIA_GITHUB_TOKEN_FILE` で参照します。実際の保存場所はリポジトリ外で管理します。

GitHub の fine-grained personal access token には対象リポジトリの
`Contents: Read and write` 権限が必要です。ファイル権限は所有者だけが読める状態にしてください。
