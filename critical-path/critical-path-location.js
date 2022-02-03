const chart_label_width = 230
const chart_row_height = 25
const chart_min_height = 84

jQuery(document).ready(function () {
  if (window.wpApiShare.url_path.startsWith('metrics/combined/critical_path_location')) {
    project_critical_path()
  }
})


function numberWithCommas(x) {
  x = (x || 0).toString();
  let pattern = /(-?\d+)(\d{3})/;
  while (pattern.test(x))
    x = x.replace(pattern, "$1,$2");
  return x;
}

// AJAX call for autocomplete
$(document).ready(function () {
  let pluginPath = dtMetricsProject.pluginsUrl + '/disciple-tools-plugin-critical-path-location/critical-path';
  $("#search-box").keyup(function () {
    $(".loading-spinner").addClass("active");
    $.ajax({
      type: "GET",
      url: pluginPath + "/get-location-grid.php",
      data: {keyword: $(this).val()},
      beforeSend: function () {
        $("#search-box").css("background", "#FFF url(LoaderIcon.gif) no-repeat 165px");
      },
      success: function (data) {
        let ul = document.createElement('ul');
        ul.setAttribute('id', 'country-list');
        arr = $.parseJSON(data); //convert to javascript array
        $('#suggesstion-box').css('border', '1px solid #ccc');
        if (arr.length > 0) {
          $.each(arr, function (key, value) {
            var li = document.createElement('li');     // create li element.
            li.innerHTML = value.name;      // assigning text to li using array value.
            let name = "'" + value.name + "'";      // assigning text to li using array value.
            li.setAttribute('onClick', 'selectCountry(' + value.grid_id + ',' + name + ')');
            li.setAttribute('style', 'display: block;');
            // remove the bullets.
            ul.appendChild(li);
          });
          $("#suggesstion-box").show();
          $("#suggesstion-box").html(ul);
        } else {
          $("#suggesstion-box").empty();
          $("#suggesstion-box").html('Nothing found! Please Try Again.');
        }
        $(".loading-spinner").removeClass("active");
        $("#search-box").css("background", "#FFF");
      }

    });
  });
});

/**
 *
 * @param gridId
 * @param name
 */
function selectCountry(gridId, name) {
  $("#search-box").val(name);
  $("#suggesstion-box").hide();
  $("#suggesstion-box").css("boder", "none");
  $('#search-box').attr('grid', gridId);
  setupLocation(
    `${dtMetricsProject.root}dt/v1/metrics/critical_path_activity_location`,
    function (data, label) {
      if (data) {
        $('.date_range_picker span').html(label);
        dtMetricsProject.data.cp = data
        fieldSelector(dtMetricsProject.data.cp)
        mediaChart(data)
        activityChart(data)
        ongoingChart(data)
        // main_chart(data)
      }
    },
    gridId
  )

}

function project_critical_path() {
  let chartDiv = jQuery('#chart')
  let translations = dtMetricsProject.translations

  jQuery('#metrics-sidemenu').foundation('down', jQuery('#combined-menu'));

  chartDiv.empty().html(`
    <div class="section-header">${window.lodash.escape(translations.title_critical_path)}</div>
    <div class="date_range_picker">
        <i class="fi-calendar"></i>&nbsp;
        <span>${moment().format("YYYY")}</span>
        <i class="dt_caret down"></i>
    </div>
    <div class="frmSearch" style="position: relative;display: inline-block;">
        <input autocomplete="off" type="text" id="search-box" placeholder="Location Name" />
        <div id="suggesstion-box" style="width: 229px;max-height: 100px;overflow-y:scroll;position: absolute;background: #fff;top: 36px;"></div>
    </div>
    <div style="display: inline-block" class="loading-spinner"></div>
    <hr>
    <div id="mediachart" style="width:90%;"></div>
    <div id="activityChart" style=" width:90%;"></div>
    <div id="ongoingChart" style="width:90%;"></div>
    <!--<div id="chartdiv" style="height: 800px; width:100%;"></div>-->
    <br>
    <h4>${window.lodash.escape(translations.filter_critical_path)}</h4>
    <div id="field_selector" style="display: flex; flex-wrap: wrap"> </div>
  `)

  fieldSelector(dtMetricsProject.data.cp)

  setupDatePickerLocation(
    `${dtMetricsProject.root}dt/v1/metrics/critical_path_activity_location`,
    function (data, label) {
      if (data) {
        $('.date_range_picker span').html(label);
        dtMetricsProject.data.cp = data
        fieldSelector(dtMetricsProject.data.cp)
        mediaChart(data)
        activityChart(data)
        ongoingChart(data)
        // main_chart(data)
      }
    },
    moment().startOf('year'),
    '',
    ''
  )
  buildCharts(dtMetricsProject.data.cp)
}

let buildCharts = function (data) {
  mediaChart(data)
  activityChart(data)
  ongoingChart(data)
  // main_chart(data)
}

let main_chart = function (data) {


  data = data.filter(a => a.outreach === undefined).reverse()

  // Create chart instance
  $('#chartdiv').empty().height(50 + chart_row_height * data.length)
  let chart = am4core.create("chartdiv", am4charts.XYChart);

  chart.data = data

  chart.legend = new am4charts.Legend();
  chart.legend.useDefaultMarker = true;

  // Create axes
  let categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
  categoryAxis.dataFields.category = "label";
  categoryAxis.renderer.grid.template.location = 0;
  categoryAxis.renderer.minGridDistance = 30;
  categoryAxis.renderer.maxGridDistance = 30;
  let valueAxis = chart.xAxes.push(new am4charts.ValueAxis());
  // valueAxis.title.text = "Critical Path";
  // valueAxis.title.fontWeight = 800;
  // valueAxis.renderer.opposite = true;
  // valueAxis.min = 1
  // console.log(max);
  // valueAxis.max = max.value * 1.1
  // console.log(valueAxis.max);


  // valueAxis.logarithmic = true;

  // Create series
  let series = chart.series.push(new am4charts.ColumnSeries());
  series.name = "Current System counts"
  series.dataFields.valueX = "total";
  series.dataFields.categoryY = "label";
  series.clustered = false;
  series.tooltipText = "Total: [bold]{valueX}[/]";

  // var valueLabel = series.bullets.push(new am4charts.LabelBullet());
  // valueLabel.label.text = "{valueX}";
  // valueLabel.label.horizontalCenter = "left";
  // valueLabel.label.dx = 10;
  // valueLabel.label.hideOversized = false;
  // valueLabel.label.truncate = false;

  let series2 = chart.series.push(new am4charts.ColumnSeries());
  series2.name = "Activity"
  series2.dataFields.valueX = "value";
  series2.dataFields.test = "value";
  series2.dataFields.categoryY = "label";
  series2.clustered = false;
  series2.columns.template.height = am4core.percent(50);
  series2.tooltipText = "[bold]{test}[/]";

  let valueLabel = series2.bullets.push(new am4charts.LabelBullet());
  valueLabel.label.text = "{valueX}";
  valueLabel.label.horizontalCenter = "left";
  valueLabel.label.dx = 10;
  valueLabel.label.hideOversized = false;
  valueLabel.label.truncate = false;


  chart.cursor = new am4charts.XYCursor();
  chart.cursor.lineX.disabled = true;
  chart.cursor.lineY.disabled = true;


  let label = categoryAxis.renderer.labels.template;
  // label.wrap = true;
  label.truncate = true
  label.maxWidth = chart_label_width;
  label.minWidth = chart_label_width;
  label.tooltipText = "{category}";
}


let setupChart = function (chart, valueX, titleText) {

  let title = chart.titles.create();
  title.text = `[bold]${titleText}[/]`;
  title.textAlign = "middle";
  title.dy = -5

  let categoryAxis = chart.yAxes.push(new am4charts.CategoryAxis());
  categoryAxis.dataFields.category = "label";
  categoryAxis.renderer.grid.template.location = 0;
  categoryAxis.renderer.minGridDistance = 10;

  let label = categoryAxis.renderer.labels.template;
  label.truncate = true
  label.maxWidth = chart_label_width;
  label.minWidth = chart_label_width;
  label.tooltipText = "{description}";
  label.textAlign = "end"
  label.dx = -5

  let valueAxis = chart.xAxes.push(new am4charts.ValueAxis());
  valueAxis.title.fontWeight = 800;
  valueAxis.renderer.grid.template.disabled = true
  valueAxis.extraMax = 0.1;
  valueAxis.min = 0
  valueAxis.paddingRight = 20;

  let series = chart.series.push(new am4charts.ColumnSeries());
  series.name = "Activity"
  series.dataFields.valueX = valueX;
  series.dataFields.categoryY = "label";
  series.clustered = false;
  series.tooltipText = "[bold]{valueX}[/]";
  series.columns.template.height = 20

  //field value label
  let valueLabel = series.bullets.push(new am4charts.LabelBullet());
  valueLabel.label.text = "{valueX}";
  valueLabel.label.horizontalCenter = "left";
  valueLabel.label.dx = 10;
  valueLabel.label.hideOversized = false;
  valueLabel.label.truncate = false;

  chart.cursor = new am4charts.XYCursor();
  chart.cursor.lineX.disabled = true;
  chart.cursor.lineY.disabled = true;
}

let mediaChart = function (data) {
  data = data.filter(a => a.outreach).reverse()
  $('#mediachart').empty().height(chart_min_height + chart_row_height * data.length)
  if (data.length) {
    let chart = am4core.create("mediachart", am4charts.XYChart);
    chart.data = data
    setupChart(chart, "outreach", dtMetricsProject.translations.title_outreach)
  }
}

let activityChart = function (data) {
  data = data.filter(a => a.type === "activity").reverse()
  $('#activityChart').empty().height(chart_min_height + chart_row_height * data.length)
  if (data.length) {
    let chart = am4core.create("activityChart", am4charts.XYChart);
    chart.data = data
    setupChart(chart, "value", dtMetricsProject.translations.title_follow_up)
  }
}
let ongoingChart = function (data) {
  data = data.filter(a => a.type === "ongoing").reverse()
  $('#ongoingChart').empty().height(chart_min_height + chart_row_height * data.length)
  if (data.length) {
    let chart = am4core.create("ongoingChart", am4charts.XYChart);
    chart.data = data
    setupChart(chart, "total", dtMetricsProject.translations.movement_training)
  }
}

let filtered = []
let fieldSelector = function (data) {
  let html = ``
  data.forEach(field => {
    let checked = !filtered.includes(field.key) ? "checked" : ""
    html += `<label style="flex-grow: 0;flex-basis:20%">
      <input type="checkbox" class="field-button" data-key="${field.key}" ${checked}>
      ${field.label}
    </label>
    `
  })
  $('#field_selector').html(html)

  $('.field-button').on("click", function () {
    let key = $(this).data("key")
    if ($(this).is(":checked")) {
      filtered = filtered.filter(a => a !== key)
    } else {
      filtered.push(key)
    }
    buildCharts(dtMetricsProject.data.cp.filter(a => !filtered.includes(a.key)))
  })

}

// custom location filter for critical path
let setupDatePickerLocation = function (endpoint_url, callback, startDate, endDate, locationGridId) {
  $(".date_range_picker").daterangepicker(
    {
      showDropdowns: true,
      ranges: date_ranges,
      linkedCalendars: false,
      locale: {
        format: "YYYY-MM-DD",
      },
      startDate: startDate || moment(0),
      endDate: endDate || moment().endOf("year").format("YYYY-MM-DD"),
    },
    function (start, end, label) {
      $(".loading-spinner").addClass("active");
      locationGridId = $('#search-box').attr('grid');
      let url = `${endpoint_url}?start=${start.format("YYYY-MM-DD")}&end=${end.format("YYYY-MM-DD")}&`;
      if (typeof locationGridId != 'undefined') {
        url = `${endpoint_url}?start=${start.format("YYYY-MM-DD")}&end=${end.format("YYYY-MM-DD")}&location=${locationGridId}&`;
      }

      jQuery
        .ajax({
          type: "GET",
          contentType: "application/json; charset=utf-8",
          dataType: "json",
          url: url,
          beforeSend: function (xhr) {
            xhr.setRequestHeader("X-WP-Nonce", wpApiShare.nonce);
          },
        })
        .done(function (data) {
          $(".loading-spinner").removeClass("active");
          if (label === "Custom Range") {
            label =
              start.format("MMMM D, YYYY") +
              " - " +
              end.format("MMMM D, YYYY");
          }
          callback(data, label, start, end);
        })
        .fail(function (err) {
          console.log("error");
          console.log(err);
          // jQuery("#errors").append(err.responseText)
        });
      // console.log('New date range selected: ' + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD') + ' (predefined range: ' + label + ')');
    }
  );
}

// get the data on searchin location

// custom location filter for critical path
let setupLocation = function (endpoint_url, callback, locationGridId) {
  let sDate;
  let eDate;
  let dateVal = $('.date_range_picker').find('span');
  let dateRange = dateVal[0].innerText;
  if (dateRange == 'All time') {
    sDate = '1970-01-01';
    eDate = new Date().getFullYear() + '-' + new Date().getDay() + '-' + new Date().getDate(); //2022-12-31
  } else {
    if (dateRange.length > 4) {
      if (dateRange.length > 20) {
        // custom datre range case
        // January 1, 2022 - January 8, 2023
        let dateArr = dateRange.split("-");
        let dateArr1 = dateArr[0].split(",");
        let dateArr2 = dateArr1[0].split(' ');
        let sDt = dateArr2[1];
        let sMonth = moment().month(dateArr2[0]).format("MM");
        let sYr = dateArr1[1];

        let dateArr11 = $.trim(dateArr[1]).split(",");
        let dateArr22 = $.trim(dateArr11[0]).split(' ');
        let eDt = dateArr22[1];
        let eMonth = moment().month(dateArr22[0]).format("MM");
        let eYr = dateArr11[1];

        sDate = $.trim(sYr) + '-' + sMonth + '-' + sDt; //2022-12-31
        eDate = $.trim(eYr) + '-' + eMonth + '-' + eDt; //2022-12-31
      } else {
        let dateArr = dateRange.split(" ");
        let start = dateArr[0];
        let end = parseInt(dateArr[1]);
        let monthNumber = moment().month(dateArr[0]).format("M");
        if (monthNumber == 1) { //january month
          sDate = moment.utc(new Date(end, 0, 2)).format("YYYY-MM-DD");
          eDate = moment.utc(new Date(end, 0, 32)).format("YYYY-MM-DD");
        } else { // december month
          sDate = moment.utc(new Date(end, 11, 2)).format("YYYY-MM-DD");
          eDate = moment.utc(new Date(end, 11, 32)).format("YYYY-MM-DD");
        }
      }

    } else {
      sDate = moment.utc(new Date(dateRange, 0, 2)).format("YYYY-MM-DD");
      eDate = moment.utc(new Date(dateRange, 11, 32)).format("YYYY-MM-DD");
    }
  }
  // return false;
  let label, start, end;
  $(".loading-spinner").addClass("active");
  jQuery
    .ajax({
      type: "GET",
      contentType: "application/json; charset=utf-8",
      dataType: "json",
      url: `${endpoint_url}?start=${sDate}&end=${eDate}&location=${locationGridId}&`,
      beforeSend: function (xhr) {
        xhr.setRequestHeader("X-WP-Nonce", wpApiShare.nonce);
      },
    })
    .done(function (data) {
      $(".loading-spinner").removeClass("active");
      if (label === "Custom Range") {
        label =
          start.format("MMMM D, YYYY") +
          " - " +
          end.format("MMMM D, YYYY");
      }
      callback(data, label, start, end);
    })
    .fail(function (err) {
      console.log("error");
      console.log(err);
    });
}

