(() => {
  const button = document.querySelector('#smitharia-regenerate');
  const status = document.querySelector('#smitharia-regenerate-status');

  if (!button || !status || !window.smithariaImages) return;

  const runBatch = async (offset = 0) => {
    const body = new URLSearchParams({
      action: 'smitharia_regenerate_images',
      nonce: window.smithariaImages.nonce,
      offset: String(offset),
    });
    const response = await fetch(window.smithariaImages.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body,
    });
    const result = await response.json();

    if (!response.ok || !result.success) {
      throw new Error(result.data?.message || `HTTP ${response.status}`);
    }

    const { processed, total, nextOffset, done, errors } = result.data;
    status.textContent = `${processed} / ${total} 件完了${errors.length ? `（失敗 ${errors.length} 件）` : ''}`;

    if (!done) await runBatch(nextOffset);
  };

  button.addEventListener('click', async () => {
    button.disabled = true;
    status.textContent = '再生成を開始しています…';
    try {
      await runBatch();
      status.textContent += '。すべて完了しました。';
    } catch (error) {
      status.textContent = `停止しました: ${error.message}`;
      button.disabled = false;
    }
  });
})();
