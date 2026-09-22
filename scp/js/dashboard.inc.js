(function($) {
    var allTabsData = {};

    $(document).ready(function() {
        allTabsData = window.irisDashboardPlotData || {};
        
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

        var activeTab = $('ul.clean.tabs li.active a').attr('href');
        if (activeTab)
            updateChart(activeTab.substring(1));
    });
    
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

        var plots = [], renderEvents = [], max = 0;
        var times = (json.times || []).slice();
        var labels = (json.labels || []).slice();
        var events = json.events || [];
        var plotData = json.plots || {};
        
        if (times.length === 0) {
            $('#line-chart-here').html('<div style="text-align:center;padding-top:50px;">No data available</div>');
            return;
        }

        events.forEach(function(e) {
            if (plotData[e] === undefined) return;

            var series = plotData[e].slice();

            $('<span>').append(e)
                .attr({'class':'label','style':'margin-left:0.5em'})
                .appendTo($('#line-chart-legend'));
            $('<br>').appendTo('#line-chart-legend');

            renderEvents.push(e);
            plots.push(series);
            max = Math.max(max, Math.max.apply(Math, series));
        });

        if (plots.length === 0) {
            $('#line-chart-here').html('<div style="text-align:center;padding-top:50px;">No data available</div>');
            return;
        }
        
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
            axisystep: Math.min(12, Math.max(1, max)),
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
                        for (var i = 0; i < renderEvents.length; i++) {
                            var event = renderEvents[i];
                            if (m.symbols[i] && m.symbols[i][colIndex] && 
                                plots[i] && plots[i][colIndex] !== undefined) {
                                caseInfo.push({
                                    type: event.charAt(0).toUpperCase() + event.slice(1),
                                    value: plots[i][colIndex],
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

        $('#line-chart-legend span.label').each(function(i) {
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
