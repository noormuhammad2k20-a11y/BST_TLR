import './bootstrap';

/**
 * Chart.js is used by the dashboard, reports and expenses pages via the global
 * `Chart`. Bundling it removes the last CDN dependency and means charts keep
 * working offline.
 */
import Chart from 'chart.js/auto';

window.Chart = Chart;
