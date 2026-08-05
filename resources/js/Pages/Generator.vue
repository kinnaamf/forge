<script setup>
import { ref, computed } from 'vue'
import VueMonacoEditor from '@guolao/vue-monaco-editor'

const prompt = ref('')
const generatedCode = ref('<!-- Generated code will show here -->')
const isGenerating = ref(false)

const generateComponent = async () => {
  if (!prompt.value || isGenerating.value) return

  isGenerating.value = true
  generatedCode.value = ''

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

    const response = await fetch('/generate', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'text/event-stream',
        'X-CSRF-TOKEN': csrfToken
      },
      body: JSON.stringify({ prompt: prompt.value })
    })

    if (!response.ok) {
      throw new Error(`Ошибка сервера: ${ response.status } ${ response.statusText }`)
    }

    if (!response.body) {
      throw new Error('ReadableStream не поддерживается или тело ответа пустое')
    }

    const reader = response.body.getReader()
    const decoder = new TextDecoder()
    let buffer = ''

    while (true) {
      const { done, value } = await reader.read()

      if (value) {
        buffer += decoder.decode(value, { stream: true })
      }

      const lines = buffer.split('\n')
      buffer = done ? '' : (lines.pop() || '')

      for (const line of lines) {
        const trimmed = line.trim()
        if (trimmed.startsWith('data: ')) {
          try {
            const rawJson = trimmed.replace(/^data:\s*/, '')
            if (!rawJson) continue

            const data = JSON.parse(rawJson)
            if (data.text) {
              generatedCode.value += data.text
            }
          } catch (e) {
            console.warn('Пропущен поврежденный чанк:', trimmed, e)
          }
        }
      }

      if (done) break
    }
  } catch (e) {
    console.error('Ошибка в процессе генерации:', e)
    generatedCode.value = `<!-- Ошибка: ${ e.message }. Проверьте вкладку Console и Network (F12) -->`
  } finally {
    isGenerating.value = false
  }
}

const iframeSrcDoc = computed(() => {
  let cleanCode = generatedCode.value.replace(/```[a-z]*\n?/gi, '').replace(/```/g, '')

  cleanCode = cleanCode.replace(/<[^>]*$/g, '')

  return `
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">
        <script src="https://cdn.tailwindcss.com/3.4.17"><\/script>
        <script src="https://unpkg.com/lucide@latest"><\/script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"><\/script>
      </head>
      <body class="bg-slate-100 p-6 flex justify-center items-center min-h-screen">
        <div id="app" class="w-full">${cleanCode}</div>
      </body>
    </html>
  `
})
</script>

<template>
  <div class="flex h-screen w-full flex-col bg-slate-900 text-white font-sans">
    <header class="flex gap-4 border-b border-slate-800 p-4">
      <input
        v-model="prompt"
        @keyup.enter="generateComponent"
        type="text"
        placeholder="Опишите интерфейс (напр. 'Карточка товара с галереей и ценой')..."
        class="flex-1 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-white outline-none focus:border-indigo-500"
      />
      <button
        @click="generateComponent"
        :disabled="isGenerating"
        class="rounded-lg bg-indigo-600 px-6 py-2 font-medium hover:bg-indigo-500 disabled:opacity-50"
      >
        {{ isGenerating ? 'Сборка...' : 'Сгенерировать' }}
      </button>
    </header>

    <main class="flex flex-1 overflow-hidden">
      <div class="w-1/2 border-r border-slate-800">
        <VueMonacoEditor
          v-model:value="generatedCode"
          theme="vs-dark"
          language="html"
          :options="{ automaticLayout: true, minimap: { enabled: false } }"
        />
      </div>
      <div class="w-1/2 bg-white">
        <iframe
          class="h-full w-full border-none"
          :srcdoc="iframeSrcDoc"
          sandbox="allow-scripts"
        ></iframe>
      </div>
    </main>
  </div>
</template>
