(() => {
  'use strict';

  const article = document.getElementById('article');
  const moreButton = document.getElementById('more');
  if (!article || !moreButton) return;

  // ============== clone from a load-time snapshot, so no earlier shuffle or hidden state leaks into later copies ==============
  const templates = Array.from(article.querySelectorAll(':scope > p'))
    .map((p) => p.cloneNode(true));

  let imagesHidden = false;

  const paragraphs = () => Array.from(article.querySelectorAll(':scope > p'));

  // ============== Fisher-Yates, an unbiased shuffle of a copy ==============
  function shuffled(list) {
    const a = list.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  // ============== rebuild every separator from scratch; hidden paragraphs still count, because they stay in the DOM ==============
  function renderSeparators() {
    article.querySelectorAll(':scope > hr').forEach((hr) => hr.remove());
    const ps = paragraphs();
    ps.forEach((p, i) => {
      const position = i + 1;
      if (position % 2 === 0 && position < ps.length) {
        p.after(document.createElement('hr'));
      }
    });
  }

  // ============== one visibility function, run after every change, so new image paragraphs inherit the current state ==============
  function applyImageVisibility() {
    paragraphs().forEach((p) => {
      if (p.querySelector('img')) p.hidden = imagesHidden;
    });
    toggleButton.setAttribute('aria-pressed', String(imagesHidden));
    toggleButton.textContent = imagesHidden ? 'Show image paragraphs' : 'Hide image paragraphs';
  }

  // ============== the second button exists only because of this script ==============
  const toggleButton = document.createElement('button');
  toggleButton.type = 'button';
  toggleButton.id = 'toggle-images';
  moreButton.after(toggleButton);

  moreButton.addEventListener('click', () => {
    const batch = shuffled(templates).map((t) => {
      const copy = t.cloneNode(true);
      copy.removeAttribute('hidden');
      // ============== strip ids so the copies never clash with each other or the originals ==============
      copy.removeAttribute('id');
      copy.querySelectorAll('[id]').forEach((el) => el.removeAttribute('id'));
      return copy;
    });
    const last = paragraphs().pop();
    // ============== the brief says after the LAST paragraph, not at the end of the article ==============
    last.after(...batch);
    renderSeparators();
    applyImageVisibility();
  });

  toggleButton.addEventListener('click', () => {
    imagesHidden = !imagesHidden;
    applyImageVisibility();
  });

  renderSeparators();
  applyImageVisibility();
})();
