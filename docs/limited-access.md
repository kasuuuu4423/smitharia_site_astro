# 限定公開の認証

共有先ごとの閲覧用ID・パスワードをWordPressで管理します。認証後に表示する作品は全共有先で共通です。WPユーザーの作成や管理画面への権限付与は不要です。

## 管理画面での操作

「ツール → 限定公開の共有先」から、共有先名・閲覧用ID・パスワード・有効／無効を登録します。

- IDは半角英数字、ハイフン、アンダースコアの3〜64文字。登録後は変更できません。
- パスワードは空白を含まない半角英数字・記号の16〜128文字。パスワード管理アプリなどで生成してください。
- パスワードはハッシュだけを保存するため、保存後に再表示できません。共有する値は保存前に控えてください。
- 編集時のパスワード欄が空なら、現在のパスワードを維持します。
- 無効化・パスワード変更は次のページ／APIアクセスから反映されます。既に表示・保存された内容は回収できません。

共有先には `https://smitharia.com/limited/` と閲覧用ID・パスワードを渡してください。IDの新規登録・変更・無効化にはサイトの再ビルドが不要です。作品の変更には従来どおり再ビルドが必要です。

## 配信の流れ

1. `/limited` と `/limited/**` をFirebase Functionsの `limitedSite` へ転送します。
2. ブラウザのBasic認証情報をFunctionsからWPへ送信します。WPは共有先の有効状態とパスワードハッシュを確認します。
3. 認証成功時だけ、Functions内の限定HTMLまたは作品APIの結果を返します。認証結果をキャッシュしません。
4. 一般公開の `/work/[id]` は限定作品を生成しません。一般向けWP REST API、ACF to REST APIの作品エンドポイント、WPの公開クエリ・フィードからも限定作品を除外します。
5. WPに接続できない場合や予期しない応答の場合は503を返し、限定内容を配信しません。

限定作品の検索除外はページの `noindex` とFunctionsの `X-Robots-Tag` で指定します。サイトマップにも限定URLを入れません。

Firebase Hostingは静的ファイルをrewriteより先に配信します。そのためビルド後に `dist/limited` を `functions/private-site/limited` へ移動します。デプロイ前にも、限定HTMLが公開ディレクトリに残っていないか確認します。

## 初回導入

本番へのデプロイは、次の準備を完了してから行ってください。接続キーは閲覧用パスワードとは別のサーバー間通信専用キーです。

1. FirebaseプロジェクトがBlazeプランであることを確認します。Functionsの本番デプロイにはBlazeプランが必要です。課金設定はこの実装では変更しません。
2. 接続キーを1つ生成します（例：`openssl rand -hex 32`）。WPの `wp-config.php` に `define('SMITHARIA_SERVICE_KEY', '生成したキー');` を追加し、更新済みSmitharia Coreを導入します。リポジトリへキーを保存しないでください。
3. 同じ値をFirebase Secret Managerへ登録します：`npx firebase-tools@13.29.1 functions:secrets:set SMITHARIA_SERVICE_KEY`。GitHub ActionsのRepository Secretにも同名・同じ値を登録します。
4. WPの共有先管理画面でテスト用IDを登録します。ローカルビルドでは `SMITHARIA_SERVICE_KEY` をプロセスの環境変数に設定し、`npm ci --prefix functions`、`npm run test:limited`、`npm run build` を実行します。
5. 準備した成果物を `npx firebase-tools@13.29.1 deploy --only functions:limited,hosting` でデプロイします。以後、GitHub ActionsもFunctionsとHostingを同時に更新します。

WPへのキー設定とプラグイン更新が先です。対応プラグインが返す `X-Smitharia-Limited-Protection: 1` がない場合、限定HTMLのビルドを停止します。キー未設定の場合もビルドを停止します。

## 本番で確認する項目

- 未認証で限定トップ・カテゴリ・詳細・会社概要・`/limited/api/posts` を開くと認証が必要になる。
- 有効な2つのIDで同じ作品が表示される。片方を無効にするとそのIDだけ401になる。パスワード変更後は旧パスワードが使えない。
- 限定作品の `/work/[id]` と未認証のWP REST詳細が404になる。WP REST一覧に `limited=include` や `only` を指定しても限定作品が返らない。
- 限定版スライダーから `/limited/work/[id]` へ移動し、無限スクロールも認証付きAPIを使う。
- WP停止時に限定ページが503になる。限定レスポンスに `Cache-Control: private, no-store` が付く。

1つのIDで認証が10回連続失敗すると、そのIDの認証を15分間停止します。管理画面でその共有先を保存し直すと失敗回数を解除できます。

## 保護対象と制約

この実装は限定ページと作品データを保護します。WordPressの `/wp-content/uploads/` にある画像・添付ファイルの直URL、外部サービスの埋め込み動画は保護対象外です。画像まで認証必須にする場合は、WP側のファイル配信も別途変更する必要があります。

WordPressに別の公開API（GraphQLなど）や独自テンプレートがある場合、このプラグイン以外の公開経路も確認してください。CDNやキャッシュプラグインでは、作品REST APIと `/smitharia/v1/*` をキャッシュ対象から除外し、導入時に既存キャッシュを削除してください。

`npm run preview` は公開側のみを表示します。限定版の動作確認にはFunctionsとHostingのエミュレーター、または本番と同じ構成の検証環境が必要です。

自動テストのPHP部分はWP境界のスタブテストです。実際のWPでの管理画面、nonce、RESTシリアライズ、共有先登録は上記の導入確認が必要です。

参考：[Firebase Hostingの配信順序](https://firebase.google.com/docs/hosting/full-config)、[FunctionsのSecret Manager連携](https://firebase.google.com/docs/functions/config-env)、[Functionsの導入要件](https://firebase.google.com/docs/functions/get-started)。
