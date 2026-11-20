const {test,before} = require('node:test')
const assert = require('node:assert/strict')
const fs = require('node:fs')
let exactAmount, exactInput, exactSum, allocateExact, appliedReceiptAmount
before(async()=> {
    const source=fs.readFileSync('resources/js/helpers/exchange-rate-math.js','utf8')
    ;({exactAmount, exactInput, exactSum, allocateExact, appliedReceiptAmount}=await import(
        `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`))
})

test('rational operations preserve large fractions, signs, cancellation and zero divisors', () => {
    assert.deepEqual(exactAmount({n:'2',d:'6'}).fraction(),{n:'1',d:'3'})
    assert.deepEqual(exactAmount({n:'-2',d:'6'}).fraction(),{n:'-1',d:'3'})
    assert.deepEqual(exactAmount({n:'0',d:'37'}).fraction(),{n:'0',d:'1'})
    assert.equal(exactAmount('0.1').plus('0.2').compare('0.3'),0)
    assert.deepEqual(exactAmount(1).dividedBy(3).plus(exactAmount(2).dividedBy(3)).fraction(),{n:'1',d:'1'})
    for (const value of ['0.000000000001','-0.000000000001','123456789012345678.123456789012','-9007199254740993.000000000001']) {
        const a=exactAmount(value), b=exactAmount({n:'87386712345',d:'100000000'})
        assert.deepEqual(a.plus(b).minus(b).fraction(),a.fraction())
        assert.deepEqual(a.times(b).dividedBy(b).fraction(),a.fraction())
        assert.equal(a.minus(a).compare('0'),0)
        assert.equal(a.compare(a.plus('0.0000000000000001')),-1)
        assert.equal(a.compare(a.minus('0.0000000000000001')),1)
    }
    assert.throws(()=>exactAmount({n:'1',d:'0'}))
    assert.throws(()=>exactAmount('1').dividedBy('0'))
    for (const value of ['1e1001','1e-1001','1e99999999999999999999999999999999999999999']) assert.throws(()=>exactAmount(value))
})

test('finite representations round half up once without changing the original fraction', () => {
    for (const [value,scale,expected] of [['1.005',2,'1.01'],['-1.005',2,'-1.01'],['0.5',0,'1'],['-0.5',0,'-1'],['-0.000000000001',2,'0.00']]) {
        const amount=exactAmount(value), original=amount.fraction()
        assert.equal(amount.final(scale),expected)
        assert.deepEqual(amount.fraction(),original)
    }
    const third=exactAmount(1).dividedBy(3)
    assert.equal(third.decimal(),'0.333333333333')
    assert.deepEqual(third.fraction(),{n:'1',d:'3'})
    assert.equal(exactAmount('12300').decimal(0),'12300')
    assert.equal(exactInput('999999999999999999.123456789012'),'999999999999999999.123456789012')
    for (const value of ['0.0000000000001','1000000000000000000',0.1]) assert.throws(()=>exactInput(value))
    for (const scale of [-1,13,1.5,Infinity]) assert.throws(()=>third.final(scale))
})

test('allocation conserves signed targets and uses original order for equal remainders', () => {
    assert.deepEqual(allocateExact('1',[1,1,1]),['0.34','0.33','0.33'])
    assert.deepEqual(allocateExact('-1',[1,1,1]),['-0.34','-0.33','-0.33'])
    for (const [total,weights,scale] of [
        ['123456789012345678.123456789012',[1,3,7],12],
        ['-9007199254740993.001',[1,1,2],2],
        ['1',[exactAmount(1).dividedBy(3),exactAmount(2).dividedBy(3)],12],
        ['0',[0,0],2], ['12',[0,1,0],0]
    ]) {
        const shares=allocateExact(total,weights,scale)
        assert.equal(exactSum(shares).final(scale),exactAmount(total).final(scale))
        if (exactAmount(total).compare(0)>0) assert.ok(shares.every(value=>exactAmount(value).compare(0)>=0))
    }
    assert.throws(()=>allocateExact('1',[0,0]))
    assert.throws(()=>allocateExact('1',[1,-1]))
    assert.deepEqual(allocateExact('1',[]),[])
})

test('receipt conversion settles original money before converting and validates its currency and rate', () => {
    assert.equal(appliedReceiptAmount({original_amount:'0.104',currency_type_id:'USD',exchange_rate:'2.56'},'VES').final(2),'0.26')
    assert.equal(appliedReceiptAmount({original_amount:'0.30',currency_type_id:'VES',exchange_rate:'3'},'USD').final(2),'0.10')
    assert.equal(appliedReceiptAmount({original_amount:'0.105',currency_type_id:'VES'},'VES').final(2),'0.11')
    for (const row of [{original_amount:'0.001',currency_type_id:'VES'},
        {original_amount:'1',currency_type_id:'EUR'},
        {original_amount:'1',currency_type_id:'USD',exchange_rate:'0'}]) assert.throws(()=>appliedReceiptAmount(row,'VES'))
})
