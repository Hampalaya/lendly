// Smoke: marketplace browse renders and Livewire search responds.
const assert = require('assert');
const BrowsePage = require('../pages/BrowsePage.cjs');

describe('smoke: browse', () => {
  it('SM-02 marketplace page shows the search box (FR-09)', async () => {
    await BrowsePage.open();
    const input = await BrowsePage.keywordInput;
    await input.waitForDisplayed({ timeout: 15000 });
    assert.ok(await input.isDisplayed());
  });

  it('SM-03 typing a search keeps the page working (FR-09)', async () => {
    await BrowsePage.open();
    await BrowsePage.search('camera');
    // Component must still be present after Livewire round-trip.
    const input = await BrowsePage.keywordInput;
    assert.ok(await input.isDisplayed());
  });
});
