# Smitharia Core

Smitharia 固有の WordPress 処理を Git 管理するためのプラグインです。

## 機能

- 画像アップロード時はattachment IDだけをキューへ入れ、WordPress Cronが
  `uploads/filtered/YYYY/MM/filtered-{filename}` をバックグラウンド生成
- 生成は最大3回まで自動再試行し、重複実行をロックで防止
- 「ツール → Smitharia Images」で待機・実行・完了・失敗件数を確認
- 「ツール → Smitharia Images」から既存画像を5件ずつ再生成
- `/wp/v2/posts?is_recommend=true` の安全なおすすめ絞り込み
- `/wp/v2/posts?limited=exclude|include|only` の限定表示絞り込み
- 投稿更新時に GitHub の `repository_dispatch` を実行

## GitHub token

トークンをプラグインやGitへ書かないでください。ホスト固有の安全な場所へ保存し、
`wp-config.php` の `SMITHARIA_GITHUB_TOKEN_FILE` で参照します。実際の保存場所はリポジトリ外で管理します。

GitHub の fine-grained personal access token には対象リポジトリの
`Contents: Read and write` 権限が必要です。ファイル権限は所有者だけが読める状態にしてください。
