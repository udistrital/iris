var allTabsData = {};

(function($) {
    $(document).ready(function() {
        $('.tab_content').each(function() {
            var tabId = $(this).attr('id');
            allTabsData[tabId] = extractTableData($(this));
        });
        
        $('ul.clean.tabs li a').click(function(e) {
            e.preventDefault();
            var tabId = $(this).attr('href').substring(1);
            
            $('ul.clean.tabs li').removeClass('active');
            $(this).parent().addClass('active');
            
            $('.tab_content').addClass('hidden');
            $('#' + tabId).removeClass('hidden');
            
            updateChart(tabId);
            return false;
        });
        
        updateChart($('ul.clean.tabs li.active a').attr('href').substring(1));
    });
    
    function extractTableData(tabContent) {
        var tabId = tabContent.attr('id');
        var isTeam = tabId === 'team';
        var isDept = tabId === 'dept';
        
        var data = {
            labels: [],
            plots: {},
            events: []
        };
        
        var rows = $('table.dashboard-stats tbody tr', tabContent);
        var totalIndex = rows.length - 1;
        
        var headerRow = rows.first();
        var headers = headerRow.find('th').map(function() {
            return $(this).text().trim().toLowerCase();
        }).get();
        
        for (var i = 1; i < headers.length; i++) {
            var eventName = headers[i].toLowerCase().replace(/\s+/g, '_');
            data.events.push(eventName);
            data.plots[eventName] = [];
        }
        
        rows.slice(1, totalIndex).each(function() {
            var cells = $(this).find('th, td');
            
            data.labels.push($(cells[0]).text().trim());
            
            for (var i = 0; i < data.events.length; i++) {
                var cellValue = parseInt($(cells[i + 1]).text()) || 0;
                data.plots[data.events[i]].push(cellValue);
            }
        });
        
        data.times = Array.from({ length: data.labels.length }, (_, i) => i);
        return data;
    }
    
    function updateChart(tabId) {
        if (allTabsData[tabId]) {
            $.drawPlots(allTabsData[tabId]);
        }
    }
    
    $.drawPlots = function(json) {
        $('#line-chart-here').empty();
        $('#line-chart-legend').empty();
        
        var r = new Raphael('line-chart-here'),
            width = $('#line-chart-here').width(),
            height = $('#line-chart-here').height();

        var plots = [], max = 0;
        var times = json.times || [];
        var labels = json.labels || [];
        
        if (times.length === 0) {
            $('#line-chart-here').html('<div style="text-align:center;padding-top:50px;">No data available</div>');
            return;
        }

        json.events.forEach(function(e) {
            if (json.plots[e] === undefined) return;

            $('<span>').append(e)
                .attr({'class':'label','style':'margin-left:0.5em'})
                .appendTo($('#line-chart-legend'));
            $('<br>').appendTo('#line-chart-legend');

            plots.push(json.plots[e]);
            max = Math.max(max, Math.max.apply(Math, json.plots[e]));
        });
        
        if (times.length === 1) {
            times.push(1);
            labels.push(labels[0]);
            
            plots.forEach(function(plot) {
                plot.push(plot[0]);
            });
        }

        var m = r.linechart(20, 0, width - 70, height,
            times, plots, {
            gutter: 20,
            width: 1.6,
            nostroke: false,
            shade: false,
            axis: "0 0 1 1",
            axisxstep: times.length - 1,
            axisystep: Math.min(12, max),
            symbol: "circle",
            smooth: false
        });

        setTimeout(function() {
            $('tspan', $('#line-chart-here')).each(function(index) {
                if (index < labels.length) {
                    let truncatedText = labels[index].length > 16 ? 
                        labels[index].substring(0, 16) + '...' : labels[index];
                    this.firstChild.textContent = truncatedText;
        
                    var textElement = $(this).closest('text');
                    var currentX = parseFloat(textElement.attr('x'));
                    var currentY = parseFloat(textElement.attr('y'));
        
                    textElement.attr({
                        transform: "rotate(50 " + currentX + " " + currentY + ")",
                        'text-anchor': 'start',
                        y: currentY 
                    });
        
                    $(this).attr('text-anchor', 'start');
                }
            });
        }, 100);

        var chartX = 20;
        var chartWidth = width - 70 - chartX;
        var colWidth = chartWidth / Math.max(1, times.length - 1);

        if (!document.getElementById("custom-tooltip-style")) {
            $('<style>').attr('id', 'custom-tooltip-style').html(`
                #custom-tooltip {
                    position: absolute;
                    background: rgba(46, 65, 61, 0.9);
                    color: #fff;
                    padding: 8px 12px;
                    border-radius: 6px;
                    font-size: 13px;
                    line-height: 1.3;
                    max-width: 220px;
                    text-align: left;
                    z-index: 1000;
                    display: none;
                    box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.3);
                    transform: translateX(-50%);
                }
                #custom-tooltip div {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    margin-top: 4px;
                }
                #custom-tooltip span {
                    width: 10px;
                    height: 10px;
                    display: inline-block;
                    border-radius: 3px;
                }
            `).appendTo('head');
            
            $('<div>').attr('id', 'custom-tooltip').appendTo('body');
        }

        for (var i = 0; i < times.length; i++) {
            var areaX = chartX;
            var areaWidth = colWidth;
            
            if (times.length === 1) {
                areaX = chartX;
                areaWidth = chartWidth;
            } else {
                if (i === 0) {
                    areaX = chartX;
                    areaWidth = colWidth / 2;
                } else if (i === times.length - 1) {
                    areaX = chartX + (i * colWidth) - (colWidth / 2);
                    areaWidth = colWidth / 2 + colWidth;
                } else {
                    areaX = chartX + (i * colWidth) - (colWidth / 2);
                    areaWidth = colWidth;
                }
            }
            
            (function(colIndex) {
                r.rect(areaX, 0, areaWidth, height).attr({
                    fill: "#fff",
                    opacity: 0,
                }).hover(
                    function() {
                        var dependencyName = labels[colIndex];
                    
                        var caseInfo = [];
                        for (var i = 0; i < json.events.length; i++) {
                            var event = json.events[i];
                            if (m.symbols[i] && m.symbols[i][colIndex] && 
                                json.plots[event] && json.plots[event][colIndex] !== undefined) {
                                caseInfo.push({
                                    type: event.charAt(0).toUpperCase() + event.slice(1),
                                    value: json.plots[event][colIndex],
                                    color: m.symbols[i][colIndex].attr('fill')
                                });
                            }
                        }
                    
                        if (caseInfo.length === 0) return;
                    
                        var tooltip = $('#custom-tooltip');
                        var tooltipContent = `<strong>${dependencyName}</strong>`;
                        
                        caseInfo.forEach(item => {
                            tooltipContent += `<div>
                                <span style="background:${item.color};"></span>
                                ${item.type}: <strong>${item.value}</strong>
                            </div>`;
                        });
                    
                        tooltip.html(tooltipContent).show();
                    
                        var firstVisibleSymbol = null;
                        for (var i = 0; i < m.symbols.length; i++) {
                            if (m.symbols[i] && m.symbols[i][colIndex] && 
                                m.symbols[i][colIndex].node.style.display !== "none") {
                                firstVisibleSymbol = m.symbols[i][colIndex];
                                break;
                            }
                        }
                        
                        var nodeBox = (firstVisibleSymbol || this).node.getBoundingClientRect();
                        var scrollX = window.scrollX || document.documentElement.scrollLeft;
                        var scrollY = window.scrollY || document.documentElement.scrollTop;
                    
                        var boxX = nodeBox.left + scrollX + nodeBox.width / 2;
                        var boxY = nodeBox.top + scrollY - tooltip.outerHeight() - 10;
                    
                        tooltip.css({left: boxX, top: boxY});
                    
                        var screenWidth = window.innerWidth;
                        var tooltipBox = tooltip[0].getBoundingClientRect();
                    
                        if (tooltipBox.left < 10) {
                            tooltip.css({left: 10, transform: 'none'});
                        } else if (tooltipBox.right > screenWidth - 10) {
                            tooltip.css({
                                left: screenWidth - tooltipBox.width - 10,
                                transform: 'none'
                            });
                        }
                    },
                    function() {
                        $('#custom-tooltip').hide();
                    }
                );
            })(i);
        }

        $('span.label').each(function(i) {
            $(this).click(function() {
                $(this).toggleClass('disabled');
                if ($(this).hasClass('disabled')) {
                    m.symbols[i].hide();
                    m.lines[i].hide();
                } else {
                    m.symbols[i].show();
                    m.lines[i].show();
                }
            }).css('background-color', Raphael.color(m.symbols[i][0].attr('fill')).hex);
        });
    };
})(window.jQuery);