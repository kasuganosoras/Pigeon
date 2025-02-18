function setTime() {
    ptime = $("#time").val();
    RefreshHome();
}

function search() {
    psearch = $("#search").val();
    RefreshHome();
}

function newpost() {
    var htmlobj = $.ajax({
        type: 'POST',
        url: "?s=newpost&seid=" + seid,
        data: {
            ispublic: $("#ispublic").val(),
            content: $("#newpost").val()
        },
        async: true,
        error: function() {
            alert("错误：" + htmlobj.responseText);
            return;
        },
        success: function() {
            $("#newpost").val("");
            RefreshHome();
            return;
        }
    });
}

function RefreshHome() {
    currentPage = '1';
    autoRefresh = true;
    var htmlobj = $.ajax({
        type: 'GET',
        url: "?s=timeline",
        data: {
            page: '1',
            time: ptime,
            user: puser,
            search: psearch
        },
        async: true,
        error: function() {
            alert("错误：" + htmlobj.responseText);
            return;
        },
        success: function() {
            var ids = htmlobj.getResponseHeader('ids');
            if (storage != ids) {
                $("#pagecontent").html(htmlobj.responseText);
                if (isBlur && storage != '') {
                    document.title = "[新消息] " + pageTitle;
                }
                storage = ids;
                $('pre code').each(function(i, block) {
                    hljs.highlightBlock(block);
                });
                $('.message img').click(function() {
                    imgsrc.src = this.src;
                    $("#imgscan").fadeIn();
                });
            }
            return;
        }
    });
}

function loadMore() {
    autoRefresh = false;
    var newPage = parseInt(currentPage) + 1;
    var htmlobj = $.ajax({
        type: 'GET',
        url: "?s=timeline",
        data: {
            ajax: 1,
            page: newPage,
            time: ptime,
            user: puser,
            search: psearch
        },
        async: true,
        error: function() {
            return;
        },
        success: function() {
            $(".loadMore").css({
                display: 'none'
            });
            $("#pagecontent").append(htmlobj.responseText);
            currentPage = newPage;
            $('.message img').click(function() {
                imgsrc.src = this.src;
                $("#imgscan").fadeIn();
            });
            return;
        }
    });
}

function deletepost(id) {
    autoRefresh = false;
    var htmlobj = $.ajax({
        type: 'GET',
        data: {
            s: "deletepost",
            id: id,
            seid: seid
        },
        async: true,
        error: function() {
            ErrorMsg("错误：" + htmlobj.responseText);
            return;
        },
        success: function() {
            storage = '';
            SuccessMsg("消息删除成功！");
            RefreshHome();
            return;
        }
    });
}

function changepublic(id, newstatus) {
    autoRefresh = false;
    var htmlobj = $.ajax({
        type: 'GET',
        data: {
            s: "changepublic",
            id: id,
            newstatus: newstatus,
            seid: seid
        },
        async: true,
        error: function() {
            ErrorMsg("错误：" + htmlobj.responseText);
            return;
        },
        success: function() {
            storage = '';
            SuccessMsg("消息状态修改成功！");
            RefreshHome();
            return;
        }
    });
}

function SuccessMsg(text) {
    $("#alert_success").html(dismissSuccess + text + "</div>");
    $("#alert_success").fadeIn(500);
}

function ErrorMsg(text) {
    $("#alert_danger").html(dismissDanger + text + "</div>");
    $("#alert_danger").fadeIn(500);
}
/* Pigeon 1.0.170 Update start */
var editid = '';
var isopenmsgbox = false;

function showmsg(text) {
    $("#messagebg").fadeIn(300);
    $("#msgcontent").html(text);
    isopenmsgbox = true;
}

function closemsg() {
    if (isopenmsgbox) {
        $("#messagebg").fadeOut(300);
        isopenmsgbox = false;
    }
};

function progressshow(text) {
    $("#messagebg").fadeIn(300);
    $("#msgcontent").text(text);
}

function progressunshow() {
    $("#messagebg").fadeOut(300);
}

function edit(id) {
    var htmlobj = $.ajax({
        type: 'GET',
        data: {
            s: "getmsg",
            id: id,
            seid: seid
        },
        async: true,
        error: function() {
            ErrorMsg("错误：" + htmlobj.responseText);
            return;
        },
        success: function() {
            editid = id;
            try {
                var data = JSON.parse(htmlobj.responseText);
                var public_0 = "";
                var public_1 = "";
                var public_2 = "";
                switch (data.public) {
                    case "0":
                        var public_0 = ' selected="selected"';
                        break;
                    case "1":
                        var public_1 = ' selected="selected"';
                        break;
                    case "2":
                        var public_2 = ' selected="selected"';
                        break;
                }
                showmsg('<p>请输入内容</p><p><textarea class="form-control newpost editpost" placeholder="在想些什么？" id="editpost">' + data.content + '</textarea></p><table style="width: 100%;margin-bottom: 12px;"><tr><td style="width: 40%;"><select class="form-control" id="edit_ispublic"><option value="0"' + public_0 + '>所有人可见</option><option value="1"' + public_1 + '>登录后可见</option><option value="2"' + public_2 + '>仅自己可见</option></select></td><td><button class="btn btn-primary pull-right" onclick="submitedit()"><i class="fa fa-twitter"></i>&nbsp;&nbsp;保存修改</button></td></tr></table>');
            } catch (e) {
                ErrorMsg("错误：" + e.message);
            }
            return;
        }
    });
}

function submitedit() {
    var htmlobj = $.ajax({
        type: 'POST',
        url: "?s=editpost&id=" + editid,
        data: {
            ispublic: $("#edit_ispublic").val(),
            content: $("#editpost").val()
        },
        async: true,
        error: function() {
            closemsg();
            alert("错误：" + htmlobj.responseText);
            return;
        },
        success: function() {
            $("#editpost").val("");
            closemsg();
            storage = '';
            SuccessMsg("消息内容保存成功！");
            RefreshHome();
            return;
        }
    });
}
/* Update end */
window.onload = function() {
    setInterval(function() {
        if (autoRefresh) {
            RefreshHome();
        }
    }, 10000);
    $('pre code').each(function(i, block) {
        hljs.highlightBlock(block);
    });
    $('.message img').click(function() {
        imgsrc.src = this.src;
        $("#imgscan").fadeIn();
    });
}
window.onblur = function() {
    isBlur = true;
}
window.onfocus = function() {
    isBlur = false;
    document.title = pageTitle;
}