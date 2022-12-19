import { MoreHorizontal } from 'react-feather';
import React, { useEffect, useState } from 'react';
import { Button } from '@/components/elements/button';
import GreyRowBox from '@/components/elements/GreyRowBox';
import { getTickets, Ticket } from '@/api/account/tickets';
import PageContentBlock from '@/components/elements/PageContentBlock';
import NewTicketDialog from '@/components/tickets/forms/NewTicketDialog';
import { format, formatDistanceToNow } from 'date-fns';

export default () => {
    const [visible, setVisible] = useState(false);
    const [tickets, setTickets] = useState<Ticket[]>();

    useEffect(() => {
        getTickets().then((d) => setTickets(d));
    }, []);

    return (
        <PageContentBlock
            title={'Tickets de suporte'}
            description={'Criar ou responder a um ticket de suporte.'}
            showFlashKey={'tickets'}
        >
            <NewTicketDialog open={visible} onClose={() => setVisible(false)} />
            {!tickets ? (
                <p className={'text-gray-400 text-center my-4'}>Não há tickets disponíveis.</p>
            ) : (
                <>
                    {tickets.map((ticket) => (
                        <GreyRowBox className={'flex-wrap md:flex-nowrap items-center my-1'} key={ticket.id}>
                            <div className={'flex items-center truncate w-full md:flex-1'}>
                                <p className={'mr-4 text-xl font-bold'}>#{ticket.id}</p>
                                <div className={'flex flex-col truncate'}>
                                    <div className={'flex items-center mb-1'}>
                                        <p className={'break-words truncate text-lg'}>{ticket.title}</p>
                                        <span className={'ml-3 text-gray-500 text-xs font-extralight hidden sm:inline'}>
                                            {ticket.status}
                                        </span>
                                    </div>
                                    <p className={'mt-1 md:mt-0 text-xs text-neutral-300 font-mono truncate'}>
                                        {ticket.content}
                                    </p>
                                </div>
                            </div>
                            {ticket.createdAt && (
                                <div className={'flex-1 md:flex-none md:w-48 mt-4 md:mt-0 md:ml-8 md:text-center'}>
                                    <p className={'text-sm'}>{format(ticket.createdAt, 'MMMM do, yyyy')}</p>
                                    <p className={'text-2xs text-neutral-500 uppercase mt-1'}>
                                        {formatDistanceToNow(ticket.createdAt, {
                                            includeSeconds: true,
                                            addSuffix: true,
                                        })}
                                    </p>
                                </div>
                            )}
                            <div className={'mt-4 md:mt-0 ml-6'} style={{ marginRight: '-0.5rem' }}>
                                <MoreHorizontal />
                            </div>
                        </GreyRowBox>
                    ))}
                </>
            )}
            <div className={'w-full flex lg:justify-end lg:items-end mt-2'}>
                <Button onClick={() => setVisible(true)}>Criar Novo Ticket</Button>
            </div>
        </PageContentBlock>
    );
};
