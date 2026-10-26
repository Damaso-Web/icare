// Saves a Blob (from an axios responseType: 'blob' call) to the user's device
// as a real file download with the given name - unlike window.open(blobUrl),
// which opens a tab (or shows a nameless blob) instead of downloading.
export function saveBlob(blob, filename = 'download') {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}