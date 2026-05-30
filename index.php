<?php

/**
 * Stephan Soller 开发的 Simple Chat v2.0.2
 * http://arkanis.de/projects/simple-chat/
 */

// 消息缓存文件的名称。你必须手动创建它，并为 Web 服务器授予读取和写入权限。
$messages_buffer_file = "messages.json";
// 缓存在文件中的最新消息条数。
// 注意，客户端的消息列表只显示 1000 条消息以避免卡顿（参见下方的 JavaScript 代码）。
$messages_buffer_size = 1000;
// 默认禁用，设置为 true 启用。将每条聊天消息追加到 chatlog.txt 文本文件中。
// 这个日志文件是没有大小限制的，因此你必须不时地清理它，否则它可能会变得非常大。
$enable_chatlog = true;

if ( isset($_POST["content"]) and isset($_POST["name"]) ) {
	// 如果消息缓存文件尚不存在，则创建它。这样我们就不需要安装引导程序，
	// 并且由于它是由执行 PHP 的进程（通常是 Web 服务器）创建的，因此它是可写的。
	if ( ! file_exists($messages_buffer_file) )
		touch($messages_buffer_file);
	
	// 打开、锁定并读取消息缓存文件
	$buffer = fopen($messages_buffer_file, "r+b");
	flock($buffer, LOCK_EX);
	$buffer_data = stream_get_contents($buffer);
	
	// 将新消息追加到缓存数据中，如果缓存为空，则从消息 id 为 0 开始
	$messages = $buffer_data ? json_decode($buffer_data, true) : [];
	$next_id = (count($messages) > 0) ? $messages[count($messages) - 1]["id"] + 1 : 0;
	$messages[] = [ "id" => $next_id, "time" => time(), "name" => $_POST["name"], "content" => $_POST["content"] ];
	
	// 如果有必要，删除旧消息以保持缓存大小
	if (count($messages) > $messages_buffer_size)
		$messages = array_slice($messages, count($messages) - $messages_buffer_size);
	
	// 重写并解锁消息文件
	ftruncate($buffer, 0);
	rewind($buffer);
	fwrite($buffer, json_encode($messages));
	flock($buffer, LOCK_UN);
	fclose($buffer);
	
	// 可选：将消息追加到日志文件（文件追加操作是原子性的）
	if ($enable_chatlog)
		file_put_contents("chatlog.txt", date("Y-m-d H:i:s") . "\t" . strtr($_POST["name"], "\t", " ") . "\t" . strtr($_POST["content"], "\t", " ") . "\n", FILE_APPEND);
	
	exit();
}

?>
<!DOCTYPE html>
<meta charset=utf-8>
<meta name=viewport content="initial-scale=1.0">
<meta name=author content="Stephan Soller">
<title>Simple Chat</title>
<script type=module>
	// 移除"正在加载…"列表项
	document.querySelector("ul#messages > li").remove()
	
	document.querySelector("form").addEventListener("submit", async event => {
		const form = event.target
		const name =  form.name.value
		const content =  form.content.value
		
		// 阻止浏览器的默认行为（发送表单数据并显示结果页面）。我们只想在不重新加载页面的情况下发送消息。
		event.preventDefault()
		
		// 只有在新消息不为空时才发送（这对于服务器也是合理的，我们不需要发送无意义的消息）
		if (name == "" || content == "")
			return
		
		// 发送 POST 请求后立即追加一条"未决（pending）"消息（尚未得到服务器确认的消息）。
		// textContent 属性会自动转义 HTML，因此没有人可以通过注入 JavaScript 代码来危害客户端。
		await fetch(form.action, { method: "POST", body: new URLSearchParams({name, content}) })
		const messageList = document.querySelector("ul#messages")
		const messageElement = messageList.querySelector("template").content.cloneNode(true)
			messageElement.querySelector("small").textContent = name
			messageElement.querySelector("span").textContent = content
		messageList.append(messageElement)
		
		messageList.scrollTop = messageList.scrollHeight
		form.content.value = ""
		form.content.focus()
	})
	
	// 表情选择功能
	const emojiBtn = document.getElementById("emoji-btn")
	const emojiPanel = document.querySelector(".emoji-panel")
	
	emojiBtn.addEventListener("click", event => {
		event.stopPropagation()
		emojiPanel.classList.toggle("show")
	})
	
	emojiPanel.addEventListener("click", event => {
		const item = event.target.closest(".emoji-item")
		if (item) {
			const contentInput = document.querySelector("input[name=content]")
			contentInput.value += item.textContent
			contentInput.focus()
			emojiPanel.classList.remove("show")
		}
	})
	
	document.addEventListener("click", event => {
		if (!emojiBtn.contains(event.target) && !emojiPanel.contains(event.target)) {
			emojiPanel.classList.remove("show")
		}
	})
	
	// 寻找新消息的轮询函数
	async function poll_for_new_messages() {
		// 我们希望浏览器每次都重新验证缓存的 messages.json file 文件。也就是说，它应该发送一个带有 
		// If-Modified-Since 请求头的条件请求。这是 Firefox 115 中的默认行为。
		// 但在 Chrome 114 中并非如此。它只直接使用缓存的响应而不进行重新验证，从而漏掉新消息。
		// 因此，我们通过一个条件请求显式告知 fetch 进行重新验证。由于命名是一件难事，
		// 实现这一功能的选项是 { cache: "no-cache" }。参见相关技术文档。
		const response = await fetch("messages.json", { cache: "no-cache" })
		
		// 如果未找到 messages.json 则什么都不做（可能文件还不存在）
		if (!response.ok)
			return
		
		const messages = await response.json()
		const messageList = document.querySelector("ul#messages")
		const messageTemplate = messageList.querySelector("template").content.querySelector("li")
		
		// 确定在插入所有新消息后，是否应将消息列表向下滚动到底部。
		// 只有在用户已经几乎位于底部（最多距离底部 50px）时才这样做。否则，当你想向上阅读旧消息时，
		// 列表每 2 秒就向下滚动一次会非常令人恼火。在修改消息列表之前检查像素距离。
		// 否则，检查结果会被移除的或新增的消息所干扰。
		const pixelDistanceFromListeBottom = messageList.scrollHeight - messageList.scrollTop - messageList.clientHeight
		const scrollToBottom = (pixelDistanceFromListeBottom < 50)
		
		// 从列表中移除未决（pending）消息（它们稍后会被来自服务器的消息替换）
		for (const li of messageList.querySelectorAll("li.pending"))
			li.remove()
		
		// 获取最后插入的消息的 ID，或者从 -1 开始（这样来自服务器的、ID 为 0 的第一条消息将自动显示）。
		const lastMessageId = parseInt(messageList.dataset.lastMessageId ?? "-1")
		
		// 为每个传来的消息添加一个列表项，但前提是我们尚未插入过它（hence 检查是否比最后插入的消息 ID 更新）。
		for (const msg of messages) {
			if (msg.id > lastMessageId) {
				const date = new Date(msg.time * 1000);
				const messageElement = messageTemplate.cloneNode(true)
					messageElement.classList.remove("pending")
					// 这里的 locales 参数设为 'zh-CN'，确保时间戳渲染为符合中文习惯的格式（例如：2026年5月23日 16:30）
					messageElement.querySelector("small").textContent = Intl.DateTimeFormat("zh-CN", { dateStyle: "medium", timeStyle: "short" }).format(date) + " " + msg.name
					messageElement.querySelector("span").textContent = msg.content
				messageList.append(messageElement)
				messageList.dataset.lastMessageId = msg.id
			}
		}
		
		// 移除列表中除最后 1000 条之外的所有消息，以防止列表极长时导致 browser 卡顿
		for (const li of Array.from(messageList.querySelectorAll("li")).slice(0, -1000))
			li.remove()
		
		// 最后向下滚动到最新消息
		if (scrollToBottom)
			messageList.scrollTop = messageList.scrollHeight - messageList.clientHeight
	}
	
	// 启动轮询函数，并每隔两秒重复一次
	poll_for_new_messages()
	setInterval(poll_for_new_messages, 2000)
</script>
<style>
	html { margin: 0em; padding: 0; }
	body { height: 100vh; box-sizing: border-box; margin: 0; padding: 2em;
		font-family: sans-serif; font-size: medium; color: #333;
		display: flex; flex-direction: column; gap: 1em; }
	body > h1 { flex: 0 0 auto; }
	body > ul#messages { flex: 1 1 auto; }
	body > form { flex: 0 0 auto; }
	
	h1 { margin: 0; padding: 0; font-size: 2em; }
	
	ul#messages { overflow: auto; margin: 0; padding: 0 3px; list-style: none; border: 1px solid gray; }
	ul#messages li { margin: 0.35em 0; padding: 0; }
	ul#messages li small { display: block; font-size: 0.59em; color: gray; }
	ul#messages li.pending { color: #aaa; }
	
	form { font-size: 1em; margin: 0; padding: 0; position: relative; }
	form p { margin: 0; padding: 0; display: flex; gap: 0.5em; }
	form p input { font-size: 1em; min-width: 0; }
	form p input[name=name] { flex: 0 1 10em; }
	form p input[name=content] { flex: 1 1 auto; }
	form p button {}
	
	h1, ul#messages, form { width: 100%; max-width: 40rem; box-sizing: border-box; margin: 0 auto; }

	.emoji-panel {
	    display: none;
	    position: absolute;
	    bottom: calc(100% + 8px);
	    right: 0;
	    background: #fff;
	    border: 1px solid #ccc;
	    border-radius: 8px;
	    padding: 8px;
	    box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
	    z-index: 100;
	}
	.emoji-panel.show { display: block; }
	.emoji-panel .emoji-grid { display: flex; flex-wrap: wrap; gap: 2px; width: 264px; }
	.emoji-panel .emoji-item { cursor: pointer; font-size: 1.6em; padding: 4px; border-radius: 4px; line-height: 1; text-align: center; flex: 0 0 40px; transition: background 0.1s; user-select: none; }
	.emoji-panel .emoji-item:hover { background: #eee; }
</style>

<h1>Simple Chat</h1>

<ul id=messages>
	<li>正在加载…</li>
	<template>
		<li class=pending>
			<small>…</small>
			<span>…</span>
		</li>
	</template>
</ul>

<form method=post action="<?= htmlentities($_SERVER["PHP_SELF"], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, "UTF-8") ?>">
	<p>
		<input type=text name=name placeholder="昵称" value="匿名用户">
		<input type=text name=content placeholder="输入消息内容..." autofocus>
		<button type=button id=emoji-btn>😊</button>
		<button>发送</button>
	</p>
	<div class=emoji-panel>
		<div class=emoji-grid>
			<span class=emoji-item>😊</span>
			<span class=emoji-item>😂</span>
			<span class=emoji-item>😍</span>
			<span class=emoji-item>🤣</span>
			<span class=emoji-item>😁</span>
			<span class=emoji-item>❤️</span>
			<span class=emoji-item>👍</span>
			<span class=emoji-item>🔥</span>
			<span class=emoji-item>🎉</span>
			<span class=emoji-item>😢</span>
			<span class=emoji-item>😡</span>
			<span class=emoji-item>😱</span>
			<span class=emoji-item>🤔</span>
			<span class=emoji-item>😴</span>
			<span class=emoji-item>🙌</span>
			<span class=emoji-item>💪</span>
			<span class=emoji-item>👏</span>
			<span class=emoji-item>🤗</span>
			<span class=emoji-item>😎</span>
			<span class=emoji-item>🥰</span>
			<span class=emoji-item>😭</span>
			<span class=emoji-item>😤</span>
			<span class=emoji-item>🤯</span>
			<span class=emoji-item>🥺</span>
			<span class=emoji-item>💀</span>
			<span class=emoji-item>☀️</span>
			<span class=emoji-item>🌟</span>
			<span class=emoji-item>🌙</span>
			<span class=emoji-item>🌈</span>
			<span class=emoji-item>🌸</span>
			<span class=emoji-item>🍕</span>
			<span class=emoji-item>🍔</span>
			<span class=emoji-item>🌮</span>
			<span class=emoji-item>🍩</span>
			<span class=emoji-item>🍻</span>
			<span class=emoji-item>🎶</span>
			<span class=emoji-item>⚽</span>
			<span class=emoji-item>🚀</span>
			<span class=emoji-item>🎮</span>
			<span class=emoji-item>📱</span>
			<span class=emoji-item>💻</span>
			<span class=emoji-item>🔮</span>
			<span class=emoji-item>🎯</span>
			<span class=emoji-item>🏆</span>
			<span class=emoji-item>👀</span>
			<span class=emoji-item>🚗</span>
			<span class=emoji-item>🏠</span>
			<span class=emoji-item>💕</span>
			<span class=emoji-item>✨</span>
			<span class=emoji-item>🌊</span>
			<span class=emoji-item>👋</span>
		</div>
	</div>
</form>
