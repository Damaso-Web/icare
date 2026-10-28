// Saves a Blob (from an axios responseType: 'blob' call) to the user's device
// as a real file download with the given name - unlike window.open(blobUrl),
// which opens a tab (or shows a nameless blob) instead of downloading.
//
// Bug (10/29/2026): the <a download> attribute is SUPPOSED to force a save
// dialog regardless of file type, but some Chromium-based browsers (Brave
// confirmed; reportedly others too) still open an image/PDF blob in a new
// tab/viewer instead of honoring it, when the blob keeps its original
// Content-Type (e.g. image/png for a PAR photo). The reliable cross-browser
// fix is to re-wrap the blob's bytes under a generic binary type before
// creating the object URL - application/octet-stream is never something a
// browser tries to preview inline, so this forces a real download every
// time, independent of what the server's Content-Type header said.
export function saveBlob(blob, filename = 'download') {
  const forced = new Blob([blob], { type: 'application/octet-stream' });
  const url = URL.createObjectURL(forced);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}