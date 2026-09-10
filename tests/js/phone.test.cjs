const {test} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
const path = require('node:path')

test('WhatsApp numbers use the current Venezuelan input contract', async () => {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/helpers/phone.js'), 'utf8')
    const {whatsappNumber} = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
    for (const [input, expected] of [
        ['0412 123-4567', '584121234567'],
        ['4121234567', '584121234567'],
        ['+58 (412) 123-4567', '584121234567'],
        ['584121234567', '584121234567'],
        ['+51 912345678', null],
        ['+1 2025550123', null],
        ['', null], [null, null], ['---', null], ['000', null],
    ]) assert.equal(whatsappNumber(input), expected, String(input))
})

test('send handlers reject foreign numbers before window, PDF or HTTP activity', async () => {
    const source = fs.readFileSync(path.join(__dirname, '../../resources/js/helpers/phone.js'), 'utf8')
    const {whatsappNumber} = await import(`data:text/javascript;base64,${Buffer.from(source).toString('base64')}`)
    const compiler = require('vue-template-compiler')
    const parser = require('@babel/parser')
    const files = [
        'resources/js/components/secondary/SendEmailDocument.vue',
        'resources/js/views/tenant/documents/partials/dialog_link_payment.vue',
        'resources/js/views/tenant/documents/partials/options.vue',
        'resources/js/views/tenant/quotations/partials/options.vue',
        'resources/js/views/tenant/dispatches/partials/finish.vue',
        'resources/js/views/tenant/dispatches/partials/options.vue',
        'resources/js/views/tenant/sale_notes/partials/options.vue',
        'resources/js/views/tenant/pos/partials/options.vue',
        'modules/QrApi/Resources/assets/js/views/QrApiTemplate.vue',
        'modules/Order/Resources/assets/js/views/order_forms/partials/options.vue',
        'modules/QrChatBuho/Resources/assets/js/views/ButtonSend.vue',
    ]
    for (const file of files) {
        const component = compiler.parseComponent(fs.readFileSync(path.join(__dirname, '../..', file), 'utf8'))
        let method
        function visit(node) {
            if (!node || typeof node !== 'object') return
            if (node.type === 'ObjectMethod' && ['clickSendWhatsapp', 'sendQrChat'].includes(node.key.name)) method = node
            for (const value of Object.values(node)) {
                if (Array.isArray(value)) value.forEach(visit)
                else if (value && typeof value === 'object') visit(value)
            }
        }
        visit(parser.parse(component.script.content, {sourceType: 'module'}))
        assert.ok(method, file)
        // Run the actual handler body with inert browser/network dependencies.
        const body = component.script.content.slice(method.body.start + 1, method.body.end - 1)
        const handler = new Function('whatsappNumber', 'window', `return ${method.async ? 'async ' : ''}function(){${body}}`)(
            whatsappNumber, {open: () => assert.fail(`${file}: window opened`)}
        )
        const messages = []
        const context = {
            form: {customer_telephone: '+51 912345678'},
            form_utilities: {customer_telephone: '+51 912345678'},
            wsPhone: '+51 912345678', loading_submit: false,
            $message: {error: message => messages.push(message)},
            $http: {post: () => assert.fail(`${file}: request sent`)},
            convertFileToBase64: () => assert.fail(`${file}: PDF requested`),
        }
        await handler.call(context)
        assert.equal(messages.length, 1, file)
        assert.equal(context.loading_submit, false, file)
    }
})
