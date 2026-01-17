<?php
// 初始化用户数据存储（会话级）
session_start();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <link rel="shortcut icon" href="./logo.png">
    <title>十中跨级频道</title>
    <style>
        /* 全局重置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        body {
            height: 100vh;
            overflow: hidden;
        }

        /* 模态框遮罩 - 黑色阴影 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }

        /* 昵称头像模态框 - 底部滑入 */
        .user-info-modal {
            width: 100%;
            background: #fff;
            padding: 20px;
            border-radius: 16px 16px 0 0;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        /* 模态框表单样式 */
        .modal-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 14px;
            color: #333;
        }
        #username {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .avatar-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 15px;
            overflow: hidden;
            border: 2px solid #eee;
        }
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #007aff;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }

        /* 核心内容容器 */
        .chat-container {
            width: 100%;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* section1 - iframe区域 */
        .chat-view {
            flex: 1;
            width: 100%;
            position: relative;
        }
        #chat-iframe {
            width: 100%;
            height: 100%;
            border: none;
            /* section1 iframe背景白色并调低透明度 - 此处调整iframe背景透明度 */
            background: rgba(255, 255, 255, 0.95);
        }

        /* section2 - 输入区域 */
        .chat-input-area {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f5f5f5;
            /* section2整体透明度调整 - 此处调整输入区域背景及元素透明度 */
            background: rgba(245, 245, 245, 0.95);
        }
        /* 返回键 - 10%宽度 */
        .back-btn {
            width: 10%;
            text-align: center;
        }
        .back-btn a {
            display: inline-block;
            width: 36px;
            height: 36px;
            line-height: 36px;
            font-size: 20px;
            color: #666;
            /* 返回键透明度调整 - 与section1保持一致 */
            color: rgba(102, 102, 102, 0.95);
            text-decoration: none;
        }
        /* 消息输入框 - 剩余宽度（扣除返回10%+表情10%+发送20%） */
        .message-input {
            width: 60%;
            padding: 0 10px;
        }
        #message {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
            resize: none;
            height: 40px;
            /* 输入框背景透明度调整 */
            background: rgba(255, 255, 255, 0.95);
        }
        /* 表情键 - 10%宽度 */
        .emoji-btn {
            width: 10%;
            text-align: center;
        }
        .emoji-btn button {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            /* 表情键透明度调整 */
            color: rgba(102, 102, 102, 0.95);
        }
        /* 发送键 - 20%宽度 */
        .send-btn {
            width: 20%;
            text-align: center;
        }
        .send-btn button {
            padding: 8px 15px;
            background: #007aff;
            color: #fff;
            border: none;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            /* 发送键透明度调整 */
            background: rgba(0, 122, 255, 0.95);
        }

        /* 表情选择模态框 */
        .emoji-modal {
            position: absolute;
            bottom: 70px;
            right: 10px;
            background: #fff;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: none;
            /* 表情模态框透明度调整 */
            background: rgba(255, 255, 255, 0.95);
        }
        .emoji-list {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 5px;
        }
        .emoji-item {
            font-size: 20px;
            cursor: pointer;
            padding: 5px;
            text-align: center;
        }

        /* 响应式适配 */
        @media (max-width: 768px) {
            .chat-input-area {
                padding: 8px;
            }
            .back-btn a, .emoji-btn button {
                font-size: 18px;
            }
            #message {
                height: 36px;
                padding: 8px;
            }
            .send-btn button {
                padding: 6px 10px;
                font-size: 13px;
            }
        }
        @media (max-width: 480px) {
            .modal-title {
                font-size: 16px;
            }
            .avatar-preview {
                width: 60px;
                height: 60px;
            }
            .chat-input-area {
                padding: 5px;
            }
            .back-btn a, .emoji-btn button {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- 昵称头像模态框 -->
    <div class="modal-overlay" id="userModal">
        <div class="user-info-modal">
            <h3 class="modal-title">完善你的信息</h3>
            <div class="form-group">
                <label for="username">昵称（最多8个字）：</label>
                <input type="text" id="username" maxlength="8" placeholder="请输入昵称">
            </div>
            <div class="form-group">
                <label>你的头像（随机分配）：</label>
                <div class="avatar-preview">
                    <img id="avatarImg" src="" alt="随机头像">
                </div>
            </div>
            <button class="submit-btn" id="submitUserInfo">确认</button>
        </div>
    </div>

    <!-- 核心聊天区域 -->
    <div class="chat-container">
        <!-- section1: iframe显示true.html -->
        <div class="chat-view">
            <iframe id="chat-iframe" src="true.html"></iframe>
        </div>

        <!-- section2: 消息输入区域 -->
        <div class="chat-input-area">
            <!-- 返回键 -->
            <div class="back-btn">
                <a href="javascript:void(0);" id="backBtn">
                    <img src="./arrow-alt-circle-left.svg" alt="返回" style="width: 25px; height: 25px;display: block;">
                </a>
            </div>
            
            <!-- 消息输入框 -->
            <div class="message-input">
                <textarea id="message" placeholder="输入消息..." maxlength="500"></textarea>
            </div>
            
            <!-- 表情键 -->
            <div class="emoji-btn">
                <button id="emojiBtn">😊</button>
                <!-- 表情选择模态框 -->
                <div class="emoji-modal" id="emojiModal">
                    <div class="emoji-list" id="emojiList">
                        <!-- 表情列表会通过JS动态生成 -->
                    </div>
                </div>
            </div>
            
            <!-- 发送键 -->
            <div class="send-btn">
                <button id="sendBtn">发送</button>
            </div>
        </div>
    </div>

    <script>
        // 全局变量
        let userAvatar = ''; // 随机头像路径
        let userName = '';   // 用户昵称
        const emojiList = ['😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣', '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰', '😘', '😗', '😙', '😚', '😋', '😛', '😜', '😝', '🤪', '😐', '😑', '😶', '🙄', '😏', '😣', '😥', '😮', '🤐', '😯', '😪', '😫', '🥱', '😴', '😌', '😛', '😜', '😝', '🤤', '😒', '😓', '😔', '😕', '🙃', '🤑', '😲', '☹️', '🙁', '😖', '😞', '😟', '😤', '😢', '😭', '😦', '😧', '😨', '😩', '🤯', '😬', '😰', '😱', '🥵', '🥶', '😳', '🤪', '😡', '😠', '🤬', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥴', '😵', '🤯', '🤠', '🥳', '🥴', '😎', '🤓', '🧐', '😕', '😜', '😝', '🤪'];

        // 1. 页面加载后生成随机头像（c1-c100.jpg）
        window.onload = function() {
            // 优先从本地存储获取用户信息
            const savedUsername = localStorage.getItem('username');
            const savedUserImg = localStorage.getItem('userimg');
            
            if (savedUsername && savedUserImg) {
                // 已有用户信息，直接赋值并隐藏模态框
                userName = savedUsername;
                userAvatar = savedUserImg;
                document.getElementById('avatarImg').src = userAvatar;
                document.getElementById('userModal').style.display = 'none';
            } else {
                // 无用户信息，生成随机头像并显示模态框
                const randomAvatarNum = Math.floor(Math.random() * 100) + 1;
                userAvatar = `img/c${randomAvatarNum}.jpg`;
                document.getElementById('avatarImg').src = userAvatar;
                document.getElementById('userModal').style.display = 'flex';
            }

            // 初始化表情列表
            initEmojiList();

            // 绑定事件
            bindEvents();
        };

        // 2. 初始化表情列表
        function initEmojiList() {
            const emojiListEl = document.getElementById('emojiList');
            emojiList.forEach(emoji => {
                const emojiItem = document.createElement('div');
                emojiItem.className = 'emoji-item';
                emojiItem.textContent = emoji;
                emojiItem.onclick = function() {
                    // 将选中的表情插入输入框
                    const messageInput = document.getElementById('message');
                    messageInput.value += emoji;
                    // 关闭表情模态框
                    document.getElementById('emojiModal').style.display = 'none';
                };
                emojiListEl.appendChild(emojiItem);
            });
        }

        // 3. 绑定所有事件
        function bindEvents() {
            // 3.1 昵称头像确认按钮事件
            document.getElementById('submitUserInfo').addEventListener('click', function() {
                const usernameInput = document.getElementById('username');
                userName = usernameInput.value.trim();
                
                // 验证昵称
                if (!userName) {
                    alert('请输入昵称！');
                    return;
                }
                if (userName.length > 8) {
                    alert('昵称不能超过8个字！');
                    return;
                }

                // 存储用户数据（本地持久化，仅清除缓存丢失）
                localStorage.setItem('username', userName);
                localStorage.setItem('userimg', userAvatar);

                // 关闭模态框
                document.getElementById('userModal').style.display = 'none';
            });

            // 3.2 表情按钮点击事件
            document.getElementById('emojiBtn').addEventListener('click', function(e) {
                e.stopPropagation();
                const emojiModal = document.getElementById('emojiModal');
                emojiModal.style.display = emojiModal.style.display === 'none' ? 'block' : 'none';
            });

            // 点击页面其他区域关闭表情模态框
            document.addEventListener('click', function() {
                document.getElementById('emojiModal').style.display = 'none';
            });

            // 3.3 发送按钮事件
            document.getElementById('sendBtn').addEventListener('click', sendMessage);

            // 3.4 回车发送消息
            document.getElementById('message').addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // 3.5 返回按钮事件（可自定义逻辑）
            document.getElementById('backBtn').addEventListener('click', function() {
                if (confirm('确定要返回吗？')) {
                    // 此处可添加返回逻辑，如返回上一页
                    window.history.back();
                }
            });
        }

        // 4. 发送消息函数
        function sendMessage() {
            // 验证用户是否已完善信息
            if (!localStorage.getItem('username') || !localStorage.getItem('userimg')) {
                alert('请先完善你的昵称和头像信息！');
                document.getElementById('userModal').style.display = 'flex';
                return;
            }

            const messageInput = document.getElementById('message');
            const message = messageInput.value.trim();
            
            
            // 验证消息
            if (!message) {
                alert('请输入消息内容！');
                return;
            }
            // 构造消息数据时增加校验
const messageData = {
    userimg: localStorage.getItem('userimg'),
    username: localStorage.getItem('username'),
    usermessage: message
};
// 检查用户信息是否变化
if (userAvatar !== localStorage.getItem('userimg')) {
    localStorage.setItem('userimg', userAvatar);
}

// 发送数据到save-message.php（替代原chat-data.json）
fetch('save-message.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    },
    body: JSON.stringify(messageData)
})
.then(response => {
    if (!response.ok) {
        throw new Error('服务器响应失败');
    }
    return response.json();
})
.then(data => {
    if (data.status === 'success') {
        // 清空输入框
        messageInput.value = '';
        
        // 关键修改：通过postMessage通知iframe（true.html）刷新消息+滚动到底部
        const chatIframe = document.getElementById('chat-iframe');
        chatIframe.contentWindow.postMessage(
            { type: 'NEW_MESSAGE' }, 
            'http://an.kijk.top' // 生产环境建议替换为具体域名，如http://yourdomain.com
        );

        // 移除原有的刷新src逻辑：避免页面重新加载
        // chatIframe.src = 'true.html?' + new Date().getTime();
    } else {
        alert('消息发送失败：' + data.msg);
    }
})
.catch(error => {
    console.error('发送错误：', error);
    alert('消息发送失败，请检查网络或服务器配置！');
});
        }

        // 禁止点击遮罩关闭模态框
        document.getElementById('userModal').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>