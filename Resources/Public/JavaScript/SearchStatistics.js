import { Chart, registerables } from './Chart/chart.esm.js';
import DateTimePicker from '@typo3/backend/date-time-picker.js';

Chart.register(...registerables);

const queriesOverTimeChartElement = document.getElementById('queriesOverTime');

if (queriesOverTimeChartElement !== null) {
  const resolveCssColor = (cssColor, fallbackColor) => {
    const colorProbe = document.createElement('span');
    const colorProbeParent = queriesOverTimeChartElement.parentElement ?? document.body;

    colorProbe.style.color = cssColor;
    colorProbe.style.pointerEvents = 'none';
    colorProbe.style.position = 'absolute';
    colorProbe.style.visibility = 'hidden';

    colorProbeParent.append(colorProbe);
    const resolvedColor = getComputedStyle(colorProbe).color;
    colorProbe.remove();

    return resolvedColor || fallbackColor;
  };

  const fallbackTextColor = getComputedStyle(document.body).color || Chart.defaults.color;
  const fallbackSurfaceColor = getComputedStyle(queriesOverTimeChartElement.parentElement).backgroundColor || 'transparent';
  const fallbackGridColor = Chart.defaults.borderColor;
  const textColor = resolveCssColor('var(--typo3-text-color-base)', fallbackTextColor);
  const mutedTextColor = resolveCssColor('var(--typo3-text-color-variant)', textColor);
  const gridColor = resolveCssColor('var(--typo3-component-border-color)', fallbackGridColor);
  const surfaceColor = resolveCssColor('var(--typo3-surface-container-lowest)', fallbackSurfaceColor);
  const tooltipSurfaceColor = resolveCssColor('var(--typo3-surface-container-high)', surfaceColor);
  const queryColor = resolveCssColor('var(--typo3-state-primary-bg)', textColor);

  Chart.defaults.color = textColor;
  Chart.defaults.borderColor = gridColor;

  new Chart(queriesOverTimeChartElement, {
    type: 'line',
    data: {
      labels: JSON.parse(queriesOverTimeChartElement.dataset.queryLabels),
      datasets: [
        {
          data: JSON.parse(queriesOverTimeChartElement.dataset.queryData),
          label: '# of Queries',

          backgroundColor: queryColor,
          borderCapStyle: 'round',
          borderColor: queryColor,
          borderJoinStyle: 'round',
          borderWidth: 3,
          fill: false,
          pointBackgroundColor: surfaceColor,
          pointBorderColor: queryColor,
          pointBorderWidth: 2,
          pointHitRadius: 10,
          pointHoverBackgroundColor: queryColor,
          pointHoverBorderColor: surfaceColor,
          pointHoverBorderWidth: 3,
          pointHoverRadius: 7,
          pointRadius: 4,
          tension: 0.2
        }
      ]
    },
    options: {
      animation: {
        duration: 0
      },
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: true,
          labels: {
            boxHeight: 12,
            boxWidth: 44,
            color: textColor,
            font: {
              weight: '600'
            },
            padding: 16
          }
        },
        tooltip: {
          backgroundColor: tooltipSurfaceColor,
          bodyColor: textColor,
          borderColor: gridColor,
          borderWidth: 1,
          cornerRadius: 3,
          titleColor: textColor
        }
      },
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
          grid: {
            borderColor: gridColor,
            color: gridColor,
            drawBorder: true
          },
          ticks: {
            color: mutedTextColor,
            font: {
              weight: '600'
            }
          }
        },
        x: {
          grid: {
            borderColor: gridColor,
            color: gridColor,
            drawOnChartArea: false
          },
          ticks: {
            color: mutedTextColor,
            font: {
              weight: '600'
            }
          }
        }
      }
    }
  });
}

document.querySelectorAll('.t3js-datetimepicker')
  .forEach(datePickerElement => { DateTimePicker.initialize(datePickerElement) });
