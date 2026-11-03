// Smoke: landing page boots and renders heading.
const assert = require('assert');
const WelcomePage = require('../pages/WelcomePage.cjs');

describe('smoke: welcome', () => {
  it('SM-01 welcome page loads with a heading', async () => {
    await WelcomePage.open();
    const h1 = await WelcomePage.heading();
    await h1.waitForDisplayed({ timeout: 15000 });
    const text = await h1.getText();
    assert.ok(text.length > 0, 'expected a non-empty h1 on /');
  });
});
